<?php

namespace Tests\Feature;

use App\Models\AcademyGroup;
use App\Models\AuditLog;
use App\Models\BookableUnit;
use App\Models\Booking;
use App\Models\Enrollment;
use App\Models\Facility;
use App\Models\GroupSchedule;
use App\Models\OperatingHour;
use App\Models\Setting;
use App\Models\Space;
use App\Models\SpaceReservation;
use App\Models\Subscription;
use App\Models\TrainingSession;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledJobsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));
    }

    public function test_overdue_pending_deposit_bookings_expire_and_release_their_space(): void
    {
        $space = Space::factory()->create();
        $overdue = Booking::factory()->pendingDeposit()->create(['deposit_due_at' => now()->subMinute()]);
        $fresh = Booking::factory()->pendingDeposit()->create(['deposit_due_at' => now()->addMinutes(10)]);
        $confirmed = Booking::factory()->create(['status' => 'confirmed', 'deposit_due_at' => now()->subHour()]);
        $reservation = SpaceReservation::create([
            'space_id' => $space->id,
            'starts_at' => $overdue->starts_at,
            'ends_at' => $overdue->ends_at,
            'booking_id' => $overdue->id,
            'is_active' => true,
        ]);

        $this->artisan('bookings:expire-unpaid')->expectsOutput('Expired 1 unpaid bookings.')->assertSuccessful();

        $this->assertSame('expired', $overdue->refresh()->status);
        $this->assertFalse($reservation->refresh()->is_active);
        $this->assertSame('pending_deposit', $fresh->refresh()->status);
        $this->assertSame('confirmed', $confirmed->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.expired', 'entity_id' => $overdue->id, 'actor_type' => 'system']);
    }

    public function test_booking_expiry_is_idempotent(): void
    {
        Booking::factory()->pendingDeposit()->create(['deposit_due_at' => now()->subMinute()]);

        $this->artisan('bookings:expire-unpaid')->assertSuccessful();
        $this->artisan('bookings:expire-unpaid')->expectsOutput('Expired 0 unpaid bookings.')->assertSuccessful();

        $this->assertSame(1, AuditLog::query()->where('action', 'booking.expired')->count());
    }

    public function test_unpaid_first_subscription_past_the_window_cancels_the_enrollment(): void
    {
        $enrollment = Enrollment::factory()->create();
        $stale = Subscription::factory()->create(['enrollment_id' => $enrollment->id, 'created_at' => now()->subHours(49)]);
        $freshEnrollment = Enrollment::factory()->create();
        $fresh = Subscription::factory()->create(['enrollment_id' => $freshEnrollment->id, 'created_at' => now()->subHours(47)]);

        $this->artisan('subscriptions:expire-unpaid')->assertSuccessful();

        $this->assertSame('cancelled', $stale->refresh()->status);
        $this->assertSame('cancelled', $enrollment->refresh()->status);
        $this->assertSame('pending_payment', $fresh->refresh()->status);
        $this->assertSame('active', $freshEnrollment->refresh()->status);
    }

    public function test_unpaid_renewal_is_cancelled_but_the_enrollment_stays_active(): void
    {
        $enrollment = Enrollment::factory()->create();
        Subscription::factory()->active()->create(['enrollment_id' => $enrollment->id]);
        $renewal = Subscription::factory()->create(['enrollment_id' => $enrollment->id, 'created_at' => now()->subHours(50)]);

        $this->artisan('subscriptions:expire-unpaid')->assertSuccessful();

        $this->assertSame('cancelled', $renewal->refresh()->status);
        $this->assertSame('active', $enrollment->refresh()->status);
    }

    public function test_payment_window_follows_the_setting(): void
    {
        Setting::query()->create(['key' => 'academy.enrollment_payment_hours', 'value' => 2]);
        $subscription = Subscription::factory()->create(['created_at' => now()->subHours(3)]);

        $this->artisan('subscriptions:expire-unpaid')->assertSuccessful();

        $this->assertSame('cancelled', $subscription->refresh()->status);
    }

    public function test_active_subscriptions_past_their_period_expire(): void
    {
        $ended = Subscription::factory()->active()->create(['period_end' => now()->subMinute()]);
        $running = Subscription::factory()->active()->create();

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame('expired', $ended->refresh()->status);
        $this->assertSame('active', $running->refresh()->status);
    }

    public function test_unanswered_waitlist_offers_are_withdrawn_after_the_window(): void
    {
        $stale = WaitlistEntry::factory()->create(['status' => 'offered', 'offered_at' => now()->subHours(25)]);
        $fresh = WaitlistEntry::factory()->create(['status' => 'offered', 'offered_at' => now()->subHours(23)]);
        $waiting = WaitlistEntry::factory()->create();

        $this->artisan('waitlist:expire-offers')->expectsOutput('Withdrew 1 waitlist offers.')->assertSuccessful();

        $this->assertSame('removed', $stale->refresh()->status);
        $this->assertSame('offered', $fresh->refresh()->status);
        $this->assertSame('waiting', $waiting->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'waitlist.offer_expired', 'entity_id' => $stale->id]);
    }

    public function test_enrollments_with_a_passed_scheduled_end_are_ended(): void
    {
        $due = Enrollment::factory()->create(['ended_at' => now()->subMinute()]);
        Subscription::factory()->active()->create(['enrollment_id' => $due->id, 'period_end' => now()->subMinute()]);
        $later = Enrollment::factory()->create(['ended_at' => now()->addDays(3)]);
        $open = Enrollment::factory()->create();

        $this->artisan('subscriptions:expire')->assertSuccessful();
        $this->artisan('enrollments:end-scheduled')->expectsOutput('Ended 1 enrollments.')->assertSuccessful();

        $this->assertSame('ended', $due->refresh()->status);
        $this->assertSame('active', $later->refresh()->status);
        $this->assertSame('active', $open->refresh()->status);
        $this->assertSame('expired', $due->subscriptions()->first()->status);
    }

    public function test_session_generation_fills_the_window_and_is_idempotent(): void
    {
        $facility = Facility::factory()->create();
        $space = Space::factory()->create(['facility_id' => $facility->id]);
        $unit = BookableUnit::factory()->create(['facility_id' => $facility->id]);
        $unit->spaces()->attach($space->id);

        foreach (range(0, 6) as $weekday) {
            OperatingHour::factory()->create(['facility_id' => $facility->id, 'weekday' => $weekday, 'open_time' => '08:00:00', 'close_time' => '22:00:00']);
        }

        $group = AcademyGroup::factory()->create(['is_active' => true]);
        $inactive = AcademyGroup::factory()->create(['is_active' => false]);
        $schedule = GroupSchedule::factory()->create(['group_id' => $group->id, 'unit_id' => $unit->id, 'weekday' => 2]);
        GroupSchedule::factory()->create(['group_id' => $inactive->id, 'unit_id' => $unit->id, 'weekday' => 3]);

        $this->artisan('sessions:generate')->assertSuccessful();

        $count = TrainingSession::query()->where('schedule_id', $schedule->id)->count();
        $this->assertGreaterThanOrEqual(3, $count);
        $this->assertSame($count, SpaceReservation::query()->where('is_active', true)->count());
        $this->assertSame(0, TrainingSession::query()->where('group_id', $inactive->id)->count());

        $this->artisan('sessions:generate')->assertSuccessful();

        $this->assertSame($count, TrainingSession::query()->where('schedule_id', $schedule->id)->count());
    }

    public function test_session_generation_skips_conflicting_schedules_and_continues(): void
    {
        $facility = Facility::factory()->create();
        $space = Space::factory()->create(['facility_id' => $facility->id]);
        $unit = BookableUnit::factory()->create(['facility_id' => $facility->id]);
        $unit->spaces()->attach($space->id);

        foreach (range(0, 6) as $weekday) {
            OperatingHour::factory()->create(['facility_id' => $facility->id, 'weekday' => $weekday, 'open_time' => '08:00:00', 'close_time' => '22:00:00']);
        }

        $blocked = GroupSchedule::factory()->create(['unit_id' => $unit->id, 'weekday' => 3, 'start_time' => '16:00:00', 'end_time' => '17:00:00']);
        $free = GroupSchedule::factory()->create(['unit_id' => $unit->id, 'weekday' => 2, 'start_time' => '18:00:00', 'end_time' => '19:00:00']);
        $booking = Booking::factory()->create(['unit_id' => $unit->id, 'starts_at' => '2026-10-28 12:00:00', 'ends_at' => '2026-10-28 15:00:00']);
        SpaceReservation::create([
            'space_id' => $space->id,
            'starts_at' => $booking->starts_at,
            'ends_at' => $booking->ends_at,
            'booking_id' => $booking->id,
            'is_active' => true,
        ]);

        $this->artisan('sessions:generate')->assertSuccessful();

        $this->assertSame(0, TrainingSession::query()->where('schedule_id', $blocked->id)->count());
        $this->assertGreaterThan(0, TrainingSession::query()->where('schedule_id', $free->id)->count());
    }
}

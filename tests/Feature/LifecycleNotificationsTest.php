<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingSeries;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\Academies\EnrollmentService;
use App\Services\Bookings\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LifecycleNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));
    }

    public function test_expiring_an_unpaid_booking_notifies_the_owner_once(): void
    {
        $booking = Booking::factory()->pendingDeposit()->create(['deposit_due_at' => now()->subMinute()]);
        $guest = Booking::factory()->pendingDeposit()->guest()->create(['deposit_due_at' => now()->subMinute()]);

        $this->artisan('bookings:expire-unpaid')->assertSuccessful();
        $this->artisan('bookings:expire-unpaid')->assertSuccessful();

        $notification = Notification::query()->where('type', 'booking_expired')->sole();
        $this->assertSame($booking->user_id, $notification->user_id);
        $this->assertSame($booking->id, $notification->data['booking_id']);
        $this->assertNotEmpty($notification->title_ar);
        $this->assertNotEmpty($notification->title_en);
        $this->assertSame('expired', $guest->refresh()->status);
    }

    public function test_cancelling_a_booking_notifies_the_owner_whether_the_user_or_staff_cancels(): void
    {
        $service = app(BookingService::class);
        $byUser = Booking::factory()->create(['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour()]);
        $byStaff = Booking::factory()->create(['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour()]);
        $guest = Booking::factory()->guest()->create(['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour()]);

        $service->cancel($byUser, $byUser->user, null, 'x');
        $service->cancel($byStaff, null, Staff::factory()->create(), 'x');
        $service->cancel($guest, null, Staff::factory()->create(), 'x');

        $this->assertSame(2, Notification::query()->where('type', 'booking_cancelled')->count());
        $this->assertSame(1, Notification::query()->where('type', 'booking_cancelled')->where('user_id', $byUser->user_id)->where('data->booking_id', $byUser->id)->count());
        $this->assertSame(1, Notification::query()->where('type', 'booking_cancelled')->where('user_id', $byStaff->user_id)->where('data->booking_id', $byStaff->id)->count());
    }

    public function test_offering_a_waitlist_seat_notifies_with_the_expiry_and_expiring_the_offer_notifies_again(): void
    {
        $entry = WaitlistEntry::factory()->create();

        app(EnrollmentService::class)->offer($entry);

        $notification = Notification::query()->where('type', 'waitlist_offered')->sole();
        $this->assertSame($entry->child->user_id, $notification->user_id);
        $this->assertSame($entry->id, $notification->data['waitlist_entry_id']);
        $this->assertSame($entry->child_id, $notification->data['child_id']);
        $this->assertSame($entry->group_id, $notification->data['group_id']);
        $this->assertSame('2026-10-27T11:00:00+03:00', $notification->data['offer_expires_at']);

        $this->travelTo(now()->addHours(25));
        $this->artisan('waitlist:expire-offers')->assertSuccessful();

        $expired = Notification::query()->where('type', 'waitlist_offer_expired')->sole();
        $this->assertSame($entry->id, $expired->data['waitlist_entry_id']);
    }

    public function test_ending_an_enrollment_and_expiring_subscriptions_notify_the_guardian(): void
    {
        $enrollment = Enrollment::factory()->create(['ended_at' => now()->subMinute()]);
        Subscription::factory()->active()->create(['enrollment_id' => $enrollment->id, 'period_end' => now()->subMinute()]);
        $stale = Subscription::factory()->create(['created_at' => now()->subHours(49)]);

        $this->artisan('subscriptions:expire')->assertSuccessful();
        $this->artisan('enrollments:end-scheduled')->assertSuccessful();
        $this->artisan('subscriptions:expire-unpaid')->assertSuccessful();
        $this->artisan('subscriptions:send-expiry-notices')->assertSuccessful();

        $ended = Notification::query()->where('type', 'enrollment_ended')->where('data->enrollment_id', $enrollment->id)->sole();
        $this->assertSame($enrollment->child_id, $ended->data['child_id']);
        $this->assertSame(1, Notification::query()->where('type', 'subscription_expired')->count());
        $unpaid = Notification::query()->where('type', 'subscription_unpaid_expired')->sole();
        $this->assertSame($stale->id, $unpaid->data['subscription_id']);
    }

    public function test_ended_confirmed_bookings_complete_and_finished_series_end(): void
    {
        $past = Booking::factory()->create(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHours(2)]);
        $running = Booking::factory()->create(['starts_at' => now()->subMinutes(30), 'ends_at' => now()->addMinutes(30)]);
        $pending = Booking::factory()->pendingDeposit()->create(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHours(2)]);
        $finished = BookingSeries::factory()->create(['first_date' => '2026-10-05', 'last_date' => '2026-10-19']);
        Booking::factory()->create(['series_id' => $finished->id, 'status' => 'completed', 'starts_at' => now()->subDays(7), 'ends_at' => now()->subDays(7)->addHour()]);
        $open = BookingSeries::factory()->create(['first_date' => '2026-10-05', 'last_date' => '2026-10-19']);
        Booking::factory()->create(['series_id' => $open->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
        $future = BookingSeries::factory()->create();

        $this->artisan('bookings:complete-past')->assertSuccessful();

        $this->assertSame('completed', $past->refresh()->status);
        $this->assertSame('confirmed', $running->refresh()->status);
        $this->assertSame('pending_deposit', $pending->refresh()->status);
        $this->assertSame('ended', $finished->refresh()->status);
        $this->assertSame('active', $open->refresh()->status);
        $this->assertSame('active', $future->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.completed', 'entity_id' => $past->id]);
    }

    public function test_cancelled_and_expired_bookings_have_no_balance_and_expose_the_refundable_amount(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $cancelled = Booking::factory()->cancelled()->create(['user_id' => $user->id, 'final_price' => 100000, 'paid_amount' => 30000]);
        $unpaid = Booking::factory()->cancelled()->create(['user_id' => $user->id, 'final_price' => 100000, 'paid_amount' => 0]);
        $expired = Booking::factory()->pendingDeposit()->create(['user_id' => $user->id, 'status' => 'expired', 'final_price' => 100000]);
        $active = Booking::factory()->create(['user_id' => $user->id, 'final_price' => 100000, 'paid_amount' => 30000]);

        $this->getJson(route('api.v1.bookings.show', $cancelled))->assertOk()
            ->assertJsonPath('data.balance_due', 0)
            ->assertJsonPath('data.paid_amount', 30000)
            ->assertJsonPath('data.refundable_amount', 30000);
        $this->getJson(route('api.v1.bookings.show', $unpaid))->assertJsonPath('data.refundable_amount', 0);
        $this->getJson(route('api.v1.bookings.show', $expired))->assertJsonPath('data.balance_due', 0)->assertJsonPath('data.refundable_amount', 0);
        $this->getJson(route('api.v1.bookings.show', $active))->assertJsonPath('data.balance_due', 70000)->assertJsonPath('data.refundable_amount', 0);
    }
}

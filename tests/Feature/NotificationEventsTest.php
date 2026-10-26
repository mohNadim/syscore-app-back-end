<?php

namespace Tests\Feature;

use App\Jobs\SendDiscountNotification;
use App\Jobs\SendPushNotification;
use App\Models\Academy;
use App\Models\AcademyGroup;
use App\Models\Attendance;
use App\Models\BookableUnit;
use App\Models\Booking;
use App\Models\Child;
use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\Facility;
use App\Models\Notification;
use App\Models\OperatingHour;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Space;
use App\Models\Sport;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Payments\PaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class NotificationEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');

        // Monday 2026-10-26 11:00 in Damascus (UTC+3).
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));
    }

    /**
     * @param  list<string>  $codes
     */
    private function actingAsStaffWith(array $codes): Staff
    {
        $role = Role::factory()->create(['name_ar' => 'دور '.fake()->unique()->numerify('####'), 'name_en' => 'Role']);

        foreach ($codes as $code) {
            $role->permissions()->attach(Permission::query()->firstOrCreate(['code' => $code], ['description' => $code]));
        }

        $staff = Staff::factory()->create(['role_id' => $role->id]);
        Sanctum::actingAs($staff);

        return $staff;
    }

    private function assertNotificationFor(User $user, string $type, array $data = []): Notification
    {
        $notification = Notification::query()->where('user_id', $user->id)->where('type', $type)->sole();

        foreach (['title_ar', 'title_en', 'body_ar', 'body_en'] as $column) {
            $this->assertNotEmpty($notification->{$column}, "{$column} should be filled");
        }

        $this->assertNotSame($notification->title_ar, $notification->title_en);

        foreach ($data as $key => $value) {
            $this->assertSame($value, $notification->data[$key]);
        }

        return $notification;
    }

    /**
     * @return array{0: Child, 1: AcademyGroup}
     */
    private function enrolledChild(?AcademyGroup $group = null): array
    {
        $group ??= AcademyGroup::factory()->create(['academy_id' => Academy::factory()->create()->id]);
        $child = Child::factory()->create();
        Enrollment::factory()->create(['child_id' => $child->id, 'group_id' => $group->id]);

        return [$child, $group];
    }

    public function test_confirming_a_booking_by_payment_notifies_the_owner_of_the_booking_and_the_payment(): void
    {
        Queue::fake();
        $booking = Booking::factory()->pendingDeposit()->create(['deposit_amount' => 10000, 'final_price' => 50000]);
        $payment = Payment::factory()->create(['user_id' => $booking->user_id, 'booking_id' => $booking->id, 'amount' => 10000, 'purpose' => 'booking_deposit']);

        app(PaymentService::class)->applySuccessfulPayment($payment);

        $this->assertSame('confirmed', $booking->refresh()->status);
        $this->assertNotificationFor($booking->user, 'booking_confirmed', ['booking_id' => $booking->id]);
        $this->assertNotificationFor($booking->user, 'payment_succeeded', ['payment_id' => $payment->id, 'booking_id' => $booking->id]);
        Queue::assertPushed(SendPushNotification::class, 2);
    }

    public function test_a_refunded_payment_on_a_closed_booking_sends_nothing(): void
    {
        Queue::fake();
        $booking = Booking::factory()->cancelled()->create();
        $payment = Payment::factory()->create(['user_id' => $booking->user_id, 'booking_id' => $booking->id, 'amount' => 10000, 'method' => 'cash']);

        try {
            app(PaymentService::class)->applySuccessfulPayment($payment);
        } catch (\Throwable) {
        }

        $this->assertSame(0, Notification::query()->where('type', 'booking_confirmed')->count());
    }

    public function test_subscription_payment_notifies_the_guardian(): void
    {
        Queue::fake();
        $subscription = Subscription::factory()->create();
        $guardian = $subscription->enrollment->child->user;
        $payment = Payment::factory()->forSubscription()->create(['user_id' => $guardian->id, 'subscription_id' => $subscription->id, 'amount' => $subscription->final_price]);

        app(PaymentService::class)->applySuccessfulPayment($payment);

        $this->assertSame('active', $subscription->refresh()->status);
        $this->assertNotificationFor($guardian, 'payment_succeeded', ['subscription_id' => $subscription->id]);
        $this->assertSame(0, Notification::query()->where('type', 'booking_confirmed')->count());
    }

    public function test_booking_reminders_go_to_confirmed_bookings_within_two_hours_once(): void
    {
        Queue::fake();
        $soon = Booking::factory()->create(['starts_at' => now()->addMinutes(90), 'ends_at' => now()->addMinutes(150)]);
        $later = Booking::factory()->create(['starts_at' => now()->addHours(3), 'ends_at' => now()->addHours(4)]);
        $pending = Booking::factory()->pendingDeposit()->create(['starts_at' => now()->addMinutes(30), 'ends_at' => now()->addMinutes(90)]);
        $guest = Booking::factory()->guest()->create(['starts_at' => now()->addMinutes(30), 'ends_at' => now()->addMinutes(90)]);

        $this->artisan('bookings:send-reminders')->expectsOutput('Sent 1 booking reminders.')->assertSuccessful();
        $this->artisan('bookings:send-reminders')->expectsOutput('Sent 0 booking reminders.')->assertSuccessful();

        $this->assertNotificationFor($soon->user, 'booking_reminder', ['booking_id' => $soon->id]);
        $this->assertSame(1, Notification::query()->where('type', 'booking_reminder')->count());
        $this->assertNotNull($soon->refresh()->reminder_sent_at);
        $this->assertNull($later->refresh()->reminder_sent_at);
        $this->assertNull($pending->refresh()->reminder_sent_at);
        $this->assertNull($guest->refresh()->reminder_sent_at);
        Queue::assertPushed(SendPushNotification::class, 1);

        // Reaching the window later still reminds the second booking exactly once.
        $this->travelTo(now()->addHour());
        $this->artisan('bookings:send-reminders')->expectsOutput('Sent 1 booking reminders.')->assertSuccessful();
        $this->assertSame(2, Notification::query()->where('type', 'booking_reminder')->count());
    }

    public function test_marking_a_child_absent_notifies_the_guardian_only_on_the_transition(): void
    {
        Queue::fake();
        $staff = $this->actingAsStaffWith(['attendance.manage']);
        [$child, $group] = $this->enrolledChild();
        [$other] = $this->enrolledChild($group);
        $session = TrainingSession::factory()->create(['group_id' => $group->id, 'starts_at' => '2026-10-26 05:00:00', 'ends_at' => '2026-10-26 06:30:00']);
        $url = "/api/v1/admin/sessions/{$session->id}/attendance";

        $this->putJson($url, ['records' => [['child_id' => $child->id, 'status' => 'absent'], ['child_id' => $other->id, 'status' => 'present']]])->assertOk();

        $this->assertNotificationFor($child->user, 'child_absent', ['child_id' => $child->id, 'session_id' => $session->id]);
        $this->assertSame(0, Notification::query()->where('user_id', $other->user_id)->count());
        Queue::assertPushed(SendPushNotification::class, 1);

        $this->putJson($url, ['records' => [['child_id' => $child->id, 'status' => 'absent']]])->assertOk();
        $this->assertSame(1, Notification::query()->where('type', 'child_absent')->count());

        $this->putJson($url, ['records' => [['child_id' => $child->id, 'status' => 'present']]])->assertOk();
        $this->putJson($url, ['records' => [['child_id' => $child->id, 'status' => 'absent']]])->assertOk();
        $this->assertSame(2, Notification::query()->where('type', 'child_absent')->count());
        $this->assertSame('absent', Attendance::query()->where('child_id', $child->id)->value('status'));
        $this->assertNotNull($staff);
    }

    public function test_a_failing_notifier_does_not_break_the_main_action(): void
    {
        $this->actingAsStaffWith(['attendance.manage']);
        [$child, $group] = $this->enrolledChild();
        $session = TrainingSession::factory()->create(['group_id' => $group->id, 'starts_at' => '2026-10-26 05:00:00', 'ends_at' => '2026-10-26 06:30:00']);

        $this->app->bind(NotificationService::class, fn () => new class extends NotificationService
        {
            public function notify(User $user, string $type, array $title, array $body, array $data = [], ?int $announcementId = null): Notification
            {
                throw new RuntimeException('push down');
            }
        });

        $this->putJson("/api/v1/admin/sessions/{$session->id}/attendance", ['records' => [['child_id' => $child->id, 'status' => 'absent']]])->assertOk();

        $this->assertSame('absent', Attendance::query()->where('child_id', $child->id)->value('status'));
        $this->assertSame(0, Notification::query()->count());

        $booking = Booking::factory()->pendingDeposit()->create(['deposit_amount' => 10000, 'final_price' => 50000]);
        $payment = Payment::factory()->create(['user_id' => $booking->user_id, 'booking_id' => $booking->id, 'amount' => 10000]);
        app(PaymentService::class)->applySuccessfulPayment($payment);

        $this->assertSame('confirmed', $booking->refresh()->status);
    }

    public function test_subscription_expiry_notices_are_sent_once_each(): void
    {
        Queue::fake();
        $expiring = Subscription::factory()->active()->create(['period_end' => now()->addDays(5)]);
        $far = Subscription::factory()->active()->create(['period_end' => now()->addDays(20)]);
        $ended = Subscription::factory()->active()->create(['period_start' => now()->subMonth(), 'period_end' => now()->subHour()]);

        $this->artisan('subscriptions:send-expiry-notices')->assertSuccessful();
        $this->artisan('subscriptions:send-expiry-notices')->assertSuccessful();

        $this->assertNotificationFor($expiring->enrollment->child->user, 'subscription_expiring', ['subscription_id' => $expiring->id]);
        $this->assertNotificationFor($ended->enrollment->child->user, 'subscription_expired', ['subscription_id' => $ended->id]);
        $this->assertSame(2, Notification::query()->count());
        $this->assertNull($far->refresh()->reminder_7d_sent_at);

        // The same subscription later expires: it gets its expiry notice once, still no duplicate warning.
        $this->travelTo(now()->addDays(6));
        $this->artisan('subscriptions:expire')->assertSuccessful();
        $this->artisan('subscriptions:send-expiry-notices')->assertSuccessful();
        $this->artisan('subscriptions:send-expiry-notices')->assertSuccessful();

        $this->assertSame(1, Notification::query()->where('type', 'subscription_expiring')->where('data->subscription_id', $expiring->id)->count());
        $this->assertSame(1, Notification::query()->where('type', 'subscription_expired')->where('data->subscription_id', $expiring->id)->count());
        Queue::assertPushed(SendPushNotification::class);
    }

    public function test_cancelling_a_session_notifies_each_guardian_once(): void
    {
        Queue::fake();
        $this->actingAsStaffWith(['sessions.manage']);
        [$child, $group] = $this->enrolledChild();
        $sibling = Child::factory()->create(['user_id' => $child->user_id]);
        Enrollment::factory()->create(['child_id' => $sibling->id, 'group_id' => $group->id]);
        [$ended] = $this->enrolledChild($group);
        $ended->enrollments()->update(['status' => 'ended']);
        [$otherGroupChild] = $this->enrolledChild();
        $session = TrainingSession::factory()->create(['group_id' => $group->id, 'starts_at' => '2026-10-28 13:00:00', 'ends_at' => '2026-10-28 14:00:00']);

        $this->postJson("/api/v1/admin/training-sessions/{$session->id}/cancel", ['reason' => 'طقس سيئ'])->assertOk();

        $this->assertNotificationFor($child->user, 'session_cancelled', ['session_id' => $session->id, 'group_id' => $group->id]);
        $this->assertSame(0, Notification::query()->where('user_id', $ended->user_id)->count());
        $this->assertSame(0, Notification::query()->where('user_id', $otherGroupChild->user_id)->count());
        Queue::assertPushed(SendPushNotification::class, 1);
    }

    public function test_updating_a_session_notifies_only_when_time_or_room_changed(): void
    {
        Queue::fake();
        $this->actingAsStaffWith(['sessions.manage']);
        $facility = Facility::factory()->create();
        $space = Space::factory()->create(['facility_id' => $facility->id]);
        $unit = BookableUnit::factory()->create(['facility_id' => $facility->id]);
        $unit->spaces()->attach($space->id);
        $unit->sports()->attach(Sport::factory()->create()->id);

        foreach (range(0, 6) as $weekday) {
            OperatingHour::factory()->create(['facility_id' => $facility->id, 'weekday' => $weekday, 'open_time' => '08:00', 'close_time' => '23:00']);
        }

        [$child, $group] = $this->enrolledChild();
        $session = TrainingSession::factory()->create(['group_id' => $group->id, 'unit_id' => $unit->id, 'starts_at' => '2026-10-27 13:00:00', 'ends_at' => '2026-10-27 14:00:00']);
        $url = "/api/v1/admin/training-sessions/{$session->id}";

        $this->patchJson($url, ['coach_id' => null, 'change_reason' => 'x'])->assertOk();
        $this->assertSame(0, Notification::query()->count());

        $this->patchJson($url, ['starts_at' => '2026-10-29 18:00', 'ends_at' => '2026-10-29 19:00', 'change_reason' => 'مباراة'])->assertOk();

        $this->assertNotificationFor($child->user, 'session_updated', ['session_id' => $session->id]);
        Queue::assertPushed(SendPushNotification::class, 1);
    }

    public function test_a_new_active_discount_is_broadcast_in_chunks_to_active_users_only(): void
    {
        Queue::fake();
        $this->actingAsStaffWith(['discounts.manage']);
        $active = User::factory()->create(['is_active' => true]);
        $inactive = User::factory()->create(['is_active' => false]);
        $payload = ['name_ar' => 'خصم الصيف', 'name_en' => 'Summer', 'type' => 'percent', 'value' => 20, 'starts_at' => '2026-10-01 00:00', 'ends_at' => '2026-11-30 23:59', 'notify_users' => true];

        $discountId = $this->postJson('/api/v1/admin/discounts', $payload)->assertCreated()->json('data.id');

        Queue::assertPushed(SendDiscountNotification::class, 1);

        $discount = Discount::query()->findOrFail($discountId);
        $job = new SendDiscountNotification($discount);
        $job->handle(app(NotificationService::class));
        $job->handle(app(NotificationService::class));

        $this->assertNotificationFor($active, 'discount_created', ['discount_id' => $discountId]);
        $this->assertSame(0, Notification::query()->where('user_id', $inactive->id)->count());
        Queue::assertPushed(SendPushNotification::class, fn (SendPushNotification $push) => true);
    }

    public function test_inactive_expired_future_or_unflagged_discounts_are_not_broadcast(): void
    {
        Queue::fake();
        $this->actingAsStaffWith(['discounts.manage']);
        $base = ['name_ar' => 'خصم', 'name_en' => 'D', 'type' => 'percent', 'value' => 10, 'starts_at' => '2026-10-01 00:00', 'ends_at' => '2026-11-30 23:59', 'notify_users' => true];

        $this->postJson('/api/v1/admin/discounts', [...$base, 'is_active' => false])->assertCreated();
        $this->postJson('/api/v1/admin/discounts', [...$base, 'starts_at' => '2026-09-01 00:00', 'ends_at' => '2026-09-30 00:00'])->assertCreated();
        $this->postJson('/api/v1/admin/discounts', [...$base, 'starts_at' => '2026-12-01 00:00', 'ends_at' => '2026-12-30 00:00'])->assertCreated();
        $this->postJson('/api/v1/admin/discounts', [...$base, 'notify_users' => false])->assertCreated();

        Queue::assertNotPushed(SendDiscountNotification::class);
    }
}

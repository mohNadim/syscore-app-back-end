<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Notifications\FcmPushSender;
use App\Services\Notifications\LogPushSender;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_service_creates_the_row_and_dispatches_the_job(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $notification = app(NotificationService::class)->notify(
            $user,
            'booking_confirmed',
            ['ar' => 'تم تأكيد الحجز', 'en' => 'Booking confirmed'],
            ['ar' => 'حجزك مؤكد', 'en' => 'Your booking is confirmed'],
            ['booking_id' => 7],
        );

        $this->assertSame($user->id, $notification->user_id);
        $this->assertDatabaseHas('notifications', ['id' => $notification->id, 'type' => 'booking_confirmed', 'title_en' => 'Booking confirmed', 'body_ar' => 'حجزك مؤكد']);
        $this->assertSame(['booking_id' => 7], $notification->refresh()->data);
        Queue::assertPushed(SendPushNotification::class, fn (SendPushNotification $job): bool => $job->notification->is($notification));
    }

    public function test_job_sets_push_sent_at_when_the_fake_sender_reaches_a_device(): void
    {
        $notification = Notification::factory()->create();
        UserDevice::factory()->for($notification->user)->create();

        SendPushNotification::dispatchSync($notification);

        $this->assertNotNull($notification->refresh()->push_sent_at);
    }

    public function test_job_leaves_push_sent_at_empty_without_devices(): void
    {
        $notification = Notification::factory()->create();

        SendPushNotification::dispatchSync($notification);

        $this->assertNull($notification->refresh()->push_sent_at);
    }

    public function test_job_skips_notifications_already_sent(): void
    {
        $sentAt = now()->subHour()->startOfSecond();
        $notification = Notification::factory()->create(['push_sent_at' => $sentAt]);
        $this->mock(PushSender::class)->shouldNotReceive('send');

        SendPushNotification::dispatchSync($notification);

        $this->assertTrue($notification->refresh()->push_sent_at->equalTo($sentAt));
    }

    public function test_fcm_sender_uses_the_user_language_and_stringifies_data(): void
    {
        $user = User::factory()->create(['preferred_lang' => 'en']);
        UserDevice::factory()->for($user)->create(['push_token' => 'good-token']);
        $notification = Notification::factory()->for($user)->create(['title_en' => 'Hello', 'body_en' => 'World', 'data' => ['booking_id' => 7]]);

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')
            ->once()
            ->withArgs(function (CloudMessage $message, array $tokens) use ($notification): bool {
                $payload = $message->jsonSerialize();

                return $tokens === ['good-token']
                    && $payload['notification']['title'] === 'Hello'
                    && $payload['notification']['body'] === 'World'
                    && $payload['data'] === ['booking_id' => '7', 'notification_id' => (string) $notification->id, 'type' => $notification->type];
            })
            ->andReturn(MulticastSendReport::withItems([SendReport::success(MessageTarget::with(MessageTarget::TOKEN, 'good-token'), ['name' => 'm1'])]));

        $this->assertTrue((new FcmPushSender($messaging))->send($notification));
    }

    public function test_fcm_sender_deletes_unknown_and_invalid_tokens(): void
    {
        $user = User::factory()->create();
        $other = UserDevice::factory()->create(['push_token' => 'other-users-token']);
        foreach (['good-token', 'unknown-token', 'invalid-token'] as $token) {
            UserDevice::factory()->for($user)->create(['push_token' => $token]);
        }
        $notification = Notification::factory()->for($user)->create();

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')->once()->andReturn(MulticastSendReport::withItems([
            SendReport::success(MessageTarget::with(MessageTarget::TOKEN, 'good-token'), ['name' => 'm1']),
            SendReport::failure(MessageTarget::with(MessageTarget::TOKEN, 'unknown-token'), NotFound::becauseTokenNotFound('unknown-token')),
            SendReport::failure(MessageTarget::with(MessageTarget::TOKEN, 'invalid-token'), new InvalidMessage('The registration token is not a valid FCM registration token')),
        ]));

        $this->assertTrue((new FcmPushSender($messaging))->send($notification));

        $this->assertEqualsCanonicalizing(['good-token'], $user->devices()->pluck('push_token')->all());
        $this->assertDatabaseHas('user_devices', ['id' => $other->id]);
    }

    public function test_fcm_sender_never_throws_and_reports_failure(): void
    {
        $user = User::factory()->create();
        UserDevice::factory()->for($user)->create(['push_token' => 'token-1']);
        $notification = Notification::factory()->for($user)->create();

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')->once()->andThrow(new RuntimeException('FCM down'));

        $this->assertFalse((new FcmPushSender($messaging))->send($notification));
        $this->assertSame(1, $user->devices()->count());
    }

    public function test_fcm_sender_does_nothing_without_devices(): void
    {
        $notification = Notification::factory()->create();
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldNotReceive('sendMulticast');

        $this->assertFalse((new FcmPushSender($messaging))->send($notification));
    }

    public function test_push_driver_defaults_to_the_fake_sender(): void
    {
        $this->assertInstanceOf(LogPushSender::class, app(PushSender::class));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');
    }

    public function test_inbox_lists_only_own_notifications_newest_first_with_both_languages(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $older = Notification::factory()->for($user)->create(['created_at' => now()->subDay(), 'data' => ['booking_id' => 5]]);
        $newer = Notification::factory()->for($user)->create(['created_at' => now()]);
        Notification::factory()->create();

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.1.title_ar', $older->title_ar)
            ->assertJsonPath('data.1.title_en', $older->title_en)
            ->assertJsonPath('data.1.body_ar', $older->body_ar)
            ->assertJsonPath('data.1.body_en', $older->body_en)
            ->assertJsonPath('data.1.data.booking_id', 5)
            ->assertJsonPath('data.1.is_read', false)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_unread_filter_and_unread_count(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        Notification::factory()->for($user)->count(2)->create();
        Notification::factory()->for($user)->create(['read_at' => now()]);
        Notification::factory()->count(3)->create();

        $this->getJson('/api/v1/notifications?unread=1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.unread_count', 2);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.unread_count', 2);
    }

    public function test_inbox_validates_query_parameters(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/notifications?unread=maybe&per_page=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['unread', 'per_page']);
    }

    public function test_user_can_mark_a_notification_as_read(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $notification = Notification::factory()->for($user)->create();

        $this->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('message', __('notifications.read', [], 'ar'))
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_user_cannot_read_another_users_notification(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $notification = Notification::factory()->create();

        $this->postJson("/api/v1/notifications/{$notification->id}/read")->assertNotFound();

        $this->assertNull($notification->refresh()->read_at);
    }

    public function test_read_all_only_affects_own_unread_notifications(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        Notification::factory()->for($user)->count(2)->create();
        $other = Notification::factory()->create();

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated', 2);

        $this->assertSame(0, $user->notifications()->whereNull('read_at')->count());
        $this->assertNull($other->refresh()->read_at);
    }

    public function test_authentication_is_required(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/read-all')->assertUnauthorized();
        $this->postJson('/api/v1/notifications/1/read')->assertUnauthorized();
        $this->deleteJson('/api/v1/notifications/1')->assertUnauthorized();
        $this->deleteJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_user_can_delete_own_notification(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $notification = Notification::factory()->for($user)->create();
        $remaining = Notification::factory()->for($user)->create();

        $this->deleteJson("/api/v1/notifications/{$notification->id}")
            ->assertOk()
            ->assertJsonPath('message', __('notifications.deleted', [], 'ar'));

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
        $this->assertDatabaseHas('notifications', ['id' => $remaining->id]);
    }

    public function test_deleting_an_unread_notification_keeps_unread_count_for_the_rest(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $unread = Notification::factory()->for($user)->create();
        Notification::factory()->for($user)->create();
        Notification::factory()->for($user)->create(['read_at' => now()]);

        $this->deleteJson("/api/v1/notifications/{$unread->id}")->assertOk();

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.unread_count', 1);
    }

    public function test_user_cannot_delete_another_users_notification(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $notification = Notification::factory()->create();

        $this->deleteJson("/api/v1/notifications/{$notification->id}")->assertNotFound();

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
    }

    public function test_delete_all_removes_only_own_notifications(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        Notification::factory()->for($user)->count(2)->create();
        Notification::factory()->for($user)->create(['read_at' => now()]);
        $other = Notification::factory()->create();

        $this->deleteJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('message', __('notifications.deleted_all', [], 'ar'))
            ->assertJsonPath('data.deleted', 3);

        $this->assertSame(0, $user->notifications()->count());
        $this->assertDatabaseHas('notifications', ['id' => $other->id]);
    }

    public function test_delete_all_with_read_filter_removes_only_read_notifications(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $unread = Notification::factory()->for($user)->count(2)->create();
        Notification::factory()->for($user)->count(2)->create(['read_at' => now()]);
        $other = Notification::factory()->create(['read_at' => now()]);

        $this->deleteJson('/api/v1/notifications?read=1')
            ->assertOk()
            ->assertJsonPath('data.deleted', 2);

        $this->assertSame(2, $user->notifications()->count());
        $this->assertSame(2, $user->notifications()->whereNull('read_at')->count());
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 2);
        $this->assertDatabaseHas('notifications', ['id' => $other->id]);
        $this->assertDatabaseHas('notifications', ['id' => $unread->first()->id]);
    }

    public function test_delete_all_validates_read_filter(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/v1/notifications?read=maybe')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['read']);
    }
}

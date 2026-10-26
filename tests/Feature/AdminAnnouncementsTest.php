<?php

namespace Tests\Feature;

use App\Jobs\SendAnnouncement;
use App\Jobs\SendPushNotification;
use App\Models\Academy;
use App\Models\AcademyGroup;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserCategory;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');
    }

    /**
     * @param  list<string>  $codes
     */
    private function staffWithPermissions(array $codes): Staff
    {
        $role = Role::factory()->create(['name_ar' => 'دور '.fake()->unique()->numerify('####'), 'name_en' => 'Role']);

        foreach ($codes as $code) {
            $permission = Permission::query()->firstOrCreate(['code' => $code], ['description' => $code]);
            $role->permissions()->attach($permission);
        }

        return Staff::factory()->create(['role_id' => $role->id]);
    }

    private function actingAsAnnouncer(): Staff
    {
        $staff = $this->staffWithPermissions(['announcements.send']);
        Sanctum::actingAs($staff);

        return $staff;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'audience' => 'all',
            'title_ar' => 'إعلان مهم',
            'title_en' => 'Important notice',
            'body_ar' => 'نص الإعلان.',
            'body_en' => 'Announcement body.',
        ], $overrides);
    }

    private function subscriberOf(Academy $academy, string $subscriptionStatus = 'active', string $enrollmentStatus = 'active'): User
    {
        $user = User::factory()->create();
        $child = Child::factory()->create(['user_id' => $user->id]);
        $group = AcademyGroup::factory()->create(['academy_id' => $academy->id]);
        $enrollment = Enrollment::factory()->create(['child_id' => $child->id, 'group_id' => $group->id, 'status' => $enrollmentStatus]);
        Subscription::factory()->create(['enrollment_id' => $enrollment->id, 'status' => $subscriptionStatus]);

        return $user;
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $announcement = Announcement::factory()->create();

        $endpoints = [
            ['getJson', '/api/v1/admin/announcements'],
            ['postJson', '/api/v1/admin/announcements'],
            ['getJson', "/api/v1/admin/announcements/{$announcement->id}"],
        ];

        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertUnauthorized();
        }

        Sanctum::actingAs(User::factory()->create());
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }

        Sanctum::actingAs($this->staffWithPermissions(['content.manage']));
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    public function test_announcements_are_immutable(): void
    {
        $this->actingAsAnnouncer();
        $announcement = Announcement::factory()->create();

        $this->putJson("/api/v1/admin/announcements/{$announcement->id}", $this->validPayload())->assertStatus(405);
        $this->deleteJson("/api/v1/admin/announcements/{$announcement->id}")->assertStatus(405);
    }

    public function test_create_stores_sent_announcement_dispatches_job_and_audits(): void
    {
        Queue::fake();
        $staff = $this->actingAsAnnouncer();

        $response = $this->postJson('/api/v1/admin/announcements', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('message', __('admin_announcements.announcement_sent', [], 'ar'))
            ->assertJsonPath('data.audience', 'all')
            ->assertJsonPath('data.academy_id', null)
            ->assertJsonPath('data.sent_by_staff_id', $staff->id);

        $announcement = Announcement::query()->findOrFail($response->json('data.id'));

        $this->assertNotNull($announcement->sent_at);
        Queue::assertPushed(SendAnnouncement::class, fn (SendAnnouncement $job) => $job->announcement->is($announcement));
        $this->assertTrue(AuditLog::query()->where('action', 'announcement.created')->where('entity_type', 'announcement')->where('entity_id', $announcement->id)->where('actor_staff_id', $staff->id)->exists());
    }

    public function test_english_fields_are_optional_and_academy_is_dropped_for_all_audience(): void
    {
        Queue::fake();
        $this->actingAsAnnouncer();
        $academy = Academy::factory()->create();

        $this->postJson('/api/v1/admin/announcements', [
            'audience' => 'all',
            'academy_id' => $academy->id,
            'title_ar' => 'عنوان',
            'body_ar' => 'نص',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title_en', null)
            ->assertJsonPath('data.body_en', null)
            ->assertJsonPath('data.academy_id', null);
    }

    public function test_create_missing_fields_return_arabic_messages(): void
    {
        $this->actingAsAnnouncer();

        $this->postJson('/api/v1/admin/announcements', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.audience.0', __('admin_announcements.validation.audience_required', [], 'ar'))
            ->assertJsonPath('errors.title_ar.0', __('admin_announcements.validation.title_ar_required', [], 'ar'))
            ->assertJsonPath('errors.body_ar.0', __('admin_announcements.validation.body_ar_required', [], 'ar'));

        $this->postJson('/api/v1/admin/announcements', $this->validPayload(['audience' => 'academy']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.academy_id.0', __('admin_announcements.validation.academy_id_required', [], 'ar'));
    }

    public function test_create_missing_fields_return_english_messages(): void
    {
        $this->actingAsAnnouncer();

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/announcements', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.audience.0', __('admin_announcements.validation.audience_required', [], 'en'))
            ->assertJsonPath('errors.title_ar.0', __('admin_announcements.validation.title_ar_required', [], 'en'))
            ->assertJsonPath('errors.body_ar.0', __('admin_announcements.validation.body_ar_required', [], 'en'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/announcements', $this->validPayload(['audience' => 'academy', 'academy_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.academy_id.0', __('admin_announcements.validation.academy_id_invalid', [], 'en'));
    }

    public function test_create_rejects_invalid_values(): void
    {
        $this->actingAsAnnouncer();

        $this->postJson('/api/v1/admin/announcements', $this->validPayload([
            'audience' => 'everyone',
            'title_ar' => ['x'],
            'title_en' => str_repeat('a', 201),
            'body_ar' => str_repeat('ن', 5001),
            'body_en' => ['x'],
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.audience.0', __('admin_announcements.validation.audience_invalid', [], 'ar'))
            ->assertJsonPath('errors.title_ar.0', __('admin_announcements.validation.title_ar_invalid', [], 'ar'))
            ->assertJsonPath('errors.title_en.0', __('admin_announcements.validation.title_en_invalid', [], 'ar'))
            ->assertJsonPath('errors.body_ar.0', __('admin_announcements.validation.body_ar_invalid', [], 'ar'))
            ->assertJsonPath('errors.body_en.0', __('admin_announcements.validation.body_en_invalid', [], 'ar'));
    }

    public function test_audience_all_reaches_every_active_user_and_not_inactive_ones(): void
    {
        Queue::fake([SendPushNotification::class]);
        $this->actingAsAnnouncer();
        $active = User::factory()->count(3)->create();
        $inactive = User::factory()->create(['is_active' => false]);

        $id = $this->postJson('/api/v1/admin/announcements', $this->validPayload())->assertCreated()->json('data.id');

        $this->assertSame(3, Notification::query()->where('announcement_id', $id)->count());
        $this->assertSame(
            $active->pluck('id')->sort()->values()->all(),
            Notification::query()->where('announcement_id', $id)->pluck('user_id')->sort()->values()->all()
        );
        $this->assertFalse(Notification::query()->where('user_id', $inactive->id)->exists());

        $notification = Notification::query()->where('announcement_id', $id)->first();
        $this->assertSame('announcement', $notification->type);
        $this->assertSame('إعلان مهم', $notification->title_ar);
        $this->assertSame('Important notice', $notification->title_en);
        $this->assertSame($id, $notification->data['announcement_id']);
        Queue::assertPushed(SendPushNotification::class, 3);
    }

    public function test_missing_english_text_falls_back_to_arabic_in_notifications(): void
    {
        Queue::fake([SendPushNotification::class]);
        $this->actingAsAnnouncer();
        User::factory()->create();

        $id = $this->postJson('/api/v1/admin/announcements', ['audience' => 'all', 'title_ar' => 'عنوان', 'body_ar' => 'نص'])->json('data.id');

        $notification = Notification::query()->where('announcement_id', $id)->firstOrFail();
        $this->assertSame('عنوان', $notification->title_en);
        $this->assertSame('نص', $notification->body_en);
    }

    public function test_audience_academy_reaches_only_active_subscribers_of_that_academy(): void
    {
        Queue::fake([SendPushNotification::class]);
        $this->actingAsAnnouncer();
        $academy = Academy::factory()->create();
        $otherAcademy = Academy::factory()->create();

        $subscriber = $this->subscriberOf($academy);
        $twoChildrenGuardian = $this->subscriberOf($academy);
        $secondChild = Child::factory()->create(['user_id' => $twoChildrenGuardian->id]);
        $secondEnrollment = Enrollment::factory()->create(['child_id' => $secondChild->id, 'group_id' => AcademyGroup::factory()->create(['academy_id' => $academy->id])->id]);
        Subscription::factory()->active()->create(['enrollment_id' => $secondEnrollment->id]);

        $this->subscriberOf($otherAcademy);
        $this->subscriberOf($academy, 'expired');
        $this->subscriberOf($academy, 'active', 'ended');
        $inactiveUser = $this->subscriberOf($academy);
        $inactiveUser->update(['is_active' => false]);
        User::factory()->create();

        $id = $this->postJson('/api/v1/admin/announcements', $this->validPayload(['audience' => 'academy', 'academy_id' => $academy->id]))
            ->assertCreated()
            ->assertJsonPath('data.academy_id', $academy->id)
            ->json('data.id');

        $this->assertEqualsCanonicalizing(
            [$subscriber->id, $twoChildrenGuardian->id],
            Notification::query()->where('announcement_id', $id)->pluck('user_id')->all()
        );
    }

    public function test_audience_category_reaches_only_active_members_of_that_category(): void
    {
        Queue::fake([SendPushNotification::class]);
        $this->actingAsAnnouncer();
        $category = UserCategory::factory()->create();
        $otherCategory = UserCategory::factory()->create();
        $member = User::factory()->create();
        $inactiveMember = User::factory()->create(['is_active' => false]);
        $otherMember = User::factory()->create();
        $category->users()->attach([$member->id, $inactiveMember->id]);
        $otherCategory->users()->attach($otherMember->id);
        User::factory()->create();

        $id = $this->postJson('/api/v1/admin/announcements', $this->validPayload([
            'audience' => 'category',
            'user_category_id' => $category->id,
            'academy_id' => Academy::factory()->create()->id,
            'user_ids' => [$otherMember->id],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.audience', 'category')
            ->assertJsonPath('data.user_category_id', $category->id)
            ->assertJsonPath('data.academy_id', null)
            ->json('data.id');

        $this->assertSame([$member->id], Notification::query()->where('announcement_id', $id)->pluck('user_id')->all());
        $this->assertSame(0, Announcement::query()->findOrFail($id)->recipients()->count());
    }

    public function test_audience_users_reaches_only_listed_active_users(): void
    {
        Queue::fake([SendPushNotification::class]);
        $this->actingAsAnnouncer();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => false]);
        User::factory()->create();

        $id = $this->postJson('/api/v1/admin/announcements', $this->validPayload([
            'audience' => 'users',
            'user_ids' => [$first->id, $second->id, $inactive->id],
            'user_category_id' => UserCategory::factory()->create()->id,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.audience', 'users')
            ->assertJsonPath('data.user_category_id', null)
            ->json('data.id');

        $this->assertEqualsCanonicalizing([$first->id, $second->id], Notification::query()->where('announcement_id', $id)->pluck('user_id')->all());
        $this->assertSame(3, Announcement::query()->findOrFail($id)->recipients()->count());

        $this->getJson("/api/v1/admin/announcements/{$id}")->assertOk()->assertJsonPath('data.recipients_count', 2);
    }

    public function test_users_audience_job_rerun_does_not_duplicate_and_reads_stored_recipients(): void
    {
        Queue::fake([SendPushNotification::class]);
        $users = User::factory()->count(2)->create();
        User::factory()->create();
        $announcement = Announcement::factory()->create(['audience' => 'users', 'sent_at' => now()]);
        $announcement->recipients()->attach($users->pluck('id'));

        (new SendAnnouncement($announcement))->handle(app(NotificationService::class));
        (new SendAnnouncement($announcement))->handle(app(NotificationService::class));

        $this->assertSame(2, Notification::query()->where('announcement_id', $announcement->id)->count());
    }

    public function test_category_and_users_audience_validation(): void
    {
        $this->actingAsAnnouncer();
        $inactiveCategory = UserCategory::factory()->create(['is_active' => false]);
        $user = User::factory()->create();

        $this->postJson('/api/v1/admin/announcements', $this->validPayload(['audience' => 'category']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.user_category_id.0', __('admin_announcements.validation.user_category_id_required', [], 'ar'));

        $this->postJson('/api/v1/admin/announcements', $this->validPayload(['audience' => 'category', 'user_category_id' => $inactiveCategory->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.user_category_id.0', __('admin_announcements.validation.user_category_id_invalid', [], 'ar'));

        $this->postJson('/api/v1/admin/announcements', $this->validPayload(['audience' => 'category', 'user_category_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_category_id');

        $this->postJson('/api/v1/admin/announcements', $this->validPayload(['audience' => 'users']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.user_ids.0', __('admin_announcements.validation.user_ids_required', [], 'ar'));

        $this->postJson('/api/v1/admin/announcements', $this->validPayload(['audience' => 'users', 'user_ids' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_ids');

        $this->postJson('/api/v1/admin/announcements', $this->validPayload(['audience' => 'users', 'user_ids' => range(1, 201)]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.user_ids.0', __('admin_announcements.validation.user_ids_invalid', [], 'ar'));

        $this->postJson('/api/v1/admin/announcements', $this->validPayload(['audience' => 'users', 'user_ids' => [$user->id, 999999]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_ids.1');

        $this->postJson('/api/v1/admin/announcements', $this->validPayload(['audience' => 'users', 'user_ids' => [$user->id, $user->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_ids.0');

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/announcements', $this->validPayload(['audience' => 'users']))
            ->assertJsonPath('errors.user_ids.0', __('admin_announcements.validation.user_ids_required', [], 'en'));

        $this->assertSame(0, Announcement::query()->count());
    }

    public function test_list_filters_by_new_audiences_and_category(): void
    {
        $this->actingAsAnnouncer();
        $category = UserCategory::factory()->create();
        $byCategory = Announcement::factory()->create(['audience' => 'category', 'user_category_id' => $category->id]);
        Announcement::factory()->create(['audience' => 'users']);

        $this->getJson('/api/v1/admin/announcements?audience=category')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $byCategory->id);
        $this->getJson('/api/v1/admin/announcements?audience=users')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/admin/announcements?user_category_id={$category->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/announcements?user_category_id=0')->assertUnprocessable()->assertJsonValidationErrors('user_category_id');
    }

    public function test_job_is_idempotent_on_rerun(): void
    {
        Queue::fake([SendPushNotification::class]);
        $users = User::factory()->count(2)->create();
        $announcement = Announcement::factory()->create(['sent_at' => now()]);

        (new SendAnnouncement($announcement))->handle(app(NotificationService::class));
        $newcomer = User::factory()->create();
        (new SendAnnouncement($announcement))->handle(app(NotificationService::class));

        $this->assertSame(3, Notification::query()->where('announcement_id', $announcement->id)->count());
        $this->assertSame(1, Notification::query()->where('announcement_id', $announcement->id)->where('user_id', $newcomer->id)->count());
        $this->assertSame(1, Notification::query()->where('announcement_id', $announcement->id)->where('user_id', $users[0]->id)->count());
        Queue::assertPushed(SendPushNotification::class, 3);
    }

    public function test_list_is_newest_first_paginated_and_filterable(): void
    {
        $this->actingAsAnnouncer();
        $academy = Academy::factory()->create();
        $first = Announcement::factory()->create(['audience' => 'all']);
        $second = Announcement::factory()->create(['audience' => 'academy', 'academy_id' => $academy->id]);
        $third = Announcement::factory()->create(['audience' => 'all']);

        $this->getJson('/api/v1/admin/announcements')
            ->assertOk()
            ->assertJsonPath('data.0.id', $third->id)
            ->assertJsonPath('data.2.id', $first->id)
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/v1/admin/announcements?per_page=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);

        $this->getJson('/api/v1/admin/announcements?audience=academy')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->id);
        $this->getJson("/api/v1/admin/announcements?academy_id={$academy->id}")->assertJsonCount(1, 'data');
    }

    public function test_list_rejects_invalid_filters(): void
    {
        $this->actingAsAnnouncer();

        $this->getJson('/api/v1/admin/announcements?audience=x&academy_id=0&per_page=100')
            ->assertUnprocessable()
            ->assertJsonPath('errors.audience.0', __('admin_announcements.validation.audience_invalid', [], 'ar'))
            ->assertJsonPath('errors.academy_id.0', __('admin_announcements.validation.academy_id_filter_invalid', [], 'ar'))
            ->assertJsonPath('errors.per_page.0', __('admin_announcements.validation.per_page_invalid', [], 'ar'));
    }

    public function test_show_includes_recipient_and_push_sent_counts(): void
    {
        $this->actingAsAnnouncer();
        $announcement = Announcement::factory()->create(['sent_at' => now()]);
        Notification::factory()->count(3)->create(['announcement_id' => $announcement->id]);
        Notification::factory()->create(['announcement_id' => $announcement->id, 'push_sent_at' => now()]);
        Notification::factory()->create();

        $this->getJson("/api/v1/admin/announcements/{$announcement->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $announcement->id)
            ->assertJsonPath('data.recipients_count', 4)
            ->assertJsonPath('data.push_sent_count', 1);

        $this->getJson('/api/v1/admin/announcements/999999')->assertNotFound();
    }
}

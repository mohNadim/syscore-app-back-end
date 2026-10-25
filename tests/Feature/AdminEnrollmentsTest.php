<?php

namespace Tests\Feature;

use App\Models\Academy;
use App\Models\AcademyGroup;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\Enrollment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\Academies\EnrollmentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminEnrollmentsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/admin/enrollments';

    private Staff $staff;

    private AcademyGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');
        $this->travelTo(CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'));
        $this->staff = $this->staffWithPermissions(['enrollments.manage']);
        Sanctum::actingAs($this->staff);
        $this->group = AcademyGroup::factory()->create([
            'academy_id' => Academy::factory()->create()->id,
            'age_min' => 6,
            'age_max' => 12,
            'gender' => 'mixed',
            'capacity' => 1,
            'monthly_price' => 100000,
        ]);
    }

    /**
     * @param  list<string>  $codes
     */
    private function staffWithPermissions(array $codes): Staff
    {
        $role = Role::factory()->create(['name_ar' => 'دور '.fake()->unique()->numerify('####'), 'name_en' => 'Role']);

        foreach ($codes as $code) {
            $role->permissions()->attach(Permission::query()->firstOrCreate(['code' => $code], ['description' => $code]));
        }

        return Staff::factory()->create(['role_id' => $role->id]);
    }

    private function child(string $gender = 'male'): Child
    {
        return Child::factory()->create(['birth_date' => '2018-01-01', 'gender' => $gender]);
    }

    public function test_staff_enrolls_a_child_and_the_staff_id_is_recorded(): void
    {
        $child = $this->child();

        $this->postJson(self::URL, ['child_id' => $child->id, 'group_id' => $this->group->id])
            ->assertCreated()
            ->assertJsonPath('message', __('admin_enrollments.created', [], 'ar'))
            ->assertJsonPath('data.created_by_staff_id', $this->staff->id)
            ->assertJsonPath('data.child.user.id', $child->user_id)
            ->assertJsonPath('data.latest_subscription.status', 'pending_payment');

        $enrollment = Enrollment::query()->sole();
        $this->assertTrue(AuditLog::query()->where('action', 'enrollment.created')->where('entity_id', $enrollment->id)->where('actor_staff_id', $this->staff->id)->exists());
    }

    public function test_full_group_waitlists_unless_capacity_is_overridden(): void
    {
        Enrollment::factory()->create(['group_id' => $this->group->id]);
        $child = $this->child();

        $this->postJson(self::URL, ['child_id' => $child->id, 'group_id' => $this->group->id])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.position', 1);

        $other = $this->child();
        $this->postJson(self::URL, ['child_id' => $other->id, 'group_id' => $this->group->id, 'override_capacity' => true])
            ->assertCreated();

        $this->assertSame(2, Enrollment::query()->where('status', 'active')->count());
        $this->assertSame(-1, app(EnrollmentService::class)->seatsLeft($this->group->fresh()));
    }

    public function test_override_enrolls_a_waiting_child_and_closes_its_entry(): void
    {
        Enrollment::factory()->create(['group_id' => $this->group->id]);
        $child = $this->child();
        $entry = WaitlistEntry::factory()->create(['group_id' => $this->group->id, 'child_id' => $child->id]);

        $this->postJson(self::URL, ['child_id' => $child->id, 'group_id' => $this->group->id])->assertConflict();
        $this->postJson(self::URL, ['child_id' => $child->id, 'group_id' => $this->group->id, 'override_capacity' => true])->assertCreated();

        $this->assertSame('enrolled', $entry->fresh()->status);
    }

    public function test_store_validation_and_eligibility(): void
    {
        $this->postJson(self::URL, [])->assertUnprocessable()
            ->assertJsonPath('errors.child_id.0', __('admin_enrollments.validation.child_id_invalid', [], 'ar'))
            ->assertJsonPath('errors.group_id.0', __('admin_enrollments.validation.group_id_invalid', [], 'ar'));

        $archived = Child::factory()->create(['is_active' => false, 'birth_date' => '2018-01-01']);
        $this->withHeader('Accept-Language', 'en')
            ->postJson(self::URL, ['child_id' => $archived->id, 'group_id' => $this->group->id, 'override_capacity' => 'maybe'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.child_id.0', __('admin_enrollments.validation.child_id_invalid', [], 'en'))
            ->assertJsonPath('errors.override_capacity.0', __('admin_enrollments.validation.override_capacity_invalid', [], 'en'));

        $tooOld = Child::factory()->create(['birth_date' => '2000-01-01']);
        $this->postJson(self::URL, ['child_id' => $tooOld->id, 'group_id' => $this->group->id, 'override_capacity' => true])->assertUnprocessable();
    }

    public function test_index_and_show(): void
    {
        $enrollment = Enrollment::factory()->create(['group_id' => $this->group->id]);
        Subscription::factory()->active()->create(['enrollment_id' => $enrollment->id]);
        $ended = Enrollment::factory()->ended()->create();

        $this->getJson(self::URL)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $ended->id);
        $this->getJson(self::URL.'?status=active')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::URL."?group_id={$this->group->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::URL."?child_id={$ended->child_id}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::URL.'?user_id='.$enrollment->child->user_id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::URL.'?status=zz')->assertUnprocessable();

        $this->getJson(self::URL."/{$enrollment->id}")->assertOk()
            ->assertJsonPath('data.group.academy.id', $this->group->academy_id)
            ->assertJsonPath('data.child.user.phone', $enrollment->child->user->phone)
            ->assertJsonPath('data.latest_subscription.status', 'active');
        $this->getJson(self::URL.'/999999')->assertNotFound();
    }

    public function test_ending_an_enrollment_cancels_subscriptions_and_frees_the_seat_for_an_offer(): void
    {
        $enrollment = Enrollment::factory()->create(['group_id' => $this->group->id]);
        $active = Subscription::factory()->active()->create(['enrollment_id' => $enrollment->id]);
        $pending = Subscription::factory()->create(['enrollment_id' => $enrollment->id]);
        $entry = WaitlistEntry::factory()->create(['group_id' => $this->group->id]);

        $this->postJson("/api/v1/admin/waitlist/{$entry->id}/offer")
            ->assertConflict()
            ->assertJsonPath('message', __('admin_enrollments.no_free_seat', [], 'ar'));

        $this->postJson(self::URL."/{$enrollment->id}/end", ['reason' => 'moved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ended');

        $this->assertSame('cancelled', $active->fresh()->status);
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertNotNull($enrollment->fresh()->ended_at);
        $this->assertTrue(AuditLog::query()->where('action', 'enrollment.ended')->where('entity_id', $enrollment->id)->exists());

        $this->postJson("/api/v1/admin/waitlist/{$entry->id}/offer")
            ->assertOk()
            ->assertJsonPath('data.status', 'offered');
        $this->assertNotNull($entry->fresh()->offered_at);
        $this->assertTrue(AuditLog::query()->where('action', 'waitlist.offered')->where('entity_id', $entry->id)->exists());

        $this->postJson(self::URL."/{$enrollment->id}/end")->assertUnprocessable();
    }

    public function test_an_offered_seat_counts_against_free_seats(): void
    {
        $first = WaitlistEntry::factory()->create(['group_id' => $this->group->id]);
        $second = WaitlistEntry::factory()->create(['group_id' => $this->group->id]);

        $this->postJson("/api/v1/admin/waitlist/{$first->id}/offer")->assertOk();
        $this->postJson("/api/v1/admin/waitlist/{$second->id}/offer")->assertConflict();
        $this->postJson("/api/v1/admin/waitlist/{$first->id}/offer")->assertUnprocessable();
    }

    public function test_waitlist_listing_is_ordered_with_positions_and_phones(): void
    {
        $first = WaitlistEntry::factory()->create(['group_id' => $this->group->id, 'created_at' => now()->subHour()]);
        $second = WaitlistEntry::factory()->create(['group_id' => $this->group->id, 'created_at' => now()]);
        WaitlistEntry::factory()->create(['group_id' => $this->group->id, 'status' => 'removed']);
        WaitlistEntry::factory()->create();

        $this->getJson("/api/v1/admin/groups/{$this->group->id}/waitlist")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.0.position', 1)
            ->assertJsonPath('data.0.child.user.phone', $first->child->user->phone)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.1.position', 2);
    }

    public function test_removing_a_waitlist_entry(): void
    {
        $entry = WaitlistEntry::factory()->create(['group_id' => $this->group->id]);

        $this->postJson("/api/v1/admin/waitlist/{$entry->id}/remove")
            ->assertOk()
            ->assertJsonPath('data.status', 'removed');
        $this->assertTrue(AuditLog::query()->where('action', 'waitlist.removed')->where('entity_id', $entry->id)->exists());
        $this->postJson("/api/v1/admin/waitlist/{$entry->id}/remove")->assertUnprocessable();
        $this->postJson('/api/v1/admin/waitlist/999999/remove')->assertNotFound();
    }

    public function test_admin_subscription_listing_and_cancellation(): void
    {
        $enrollment = Enrollment::factory()->create(['group_id' => $this->group->id]);
        $expiring = Subscription::factory()->active()->create(['enrollment_id' => $enrollment->id, 'period_end' => now()->addDays(3)]);
        Subscription::factory()->active()->create(['period_end' => now()->addDays(20)]);
        Subscription::factory()->create();

        $url = '/api/v1/admin/subscriptions';
        $this->getJson($url)->assertOk()->assertJsonCount(3, 'data');
        $this->getJson($url.'?status=active')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson($url.'?expiring_within_days=7')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $expiring->id);
        $this->getJson($url."?group_id={$this->group->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson($url.'?expiring_within_days=-1')->assertUnprocessable();

        $this->postJson("{$url}/{$expiring->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame('ended', $enrollment->fresh()->status);
        $this->postJson("{$url}/{$expiring->id}/cancel")->assertUnprocessable()
            ->assertJsonPath('message', __('admin_enrollments.subscription_not_cancellable', [], 'ar'));
    }

    public function test_cancelling_a_pending_subscription_cancels_a_never_paid_enrollment(): void
    {
        $enrollment = Enrollment::factory()->create(['group_id' => $this->group->id]);
        $pending = Subscription::factory()->create(['enrollment_id' => $enrollment->id]);

        $this->postJson("/api/v1/admin/subscriptions/{$pending->id}/cancel")->assertOk();

        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertSame('cancelled', $enrollment->fresh()->status);
    }

    public function test_access_control(): void
    {
        $paths = [
            ['getJson', self::URL],
            ['postJson', self::URL],
            ['postJson', self::URL.'/1/end'],
            ['getJson', '/api/v1/admin/subscriptions'],
            ['postJson', '/api/v1/admin/subscriptions/1/cancel'],
            ['getJson', '/api/v1/admin/groups/1/waitlist'],
            ['postJson', '/api/v1/admin/waitlist/1/offer'],
            ['postJson', '/api/v1/admin/waitlist/1/remove'],
        ];

        Sanctum::actingAs($this->staffWithPermissions(['bookings.view']));
        foreach ($paths as [$method, $path]) {
            $this->{$method}($path)->assertForbidden();
        }

        Sanctum::actingAs(User::factory()->create());
        foreach ($paths as [$method, $path]) {
            $this->{$method}($path)->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->getJson(self::URL)->assertUnauthorized();
    }
}

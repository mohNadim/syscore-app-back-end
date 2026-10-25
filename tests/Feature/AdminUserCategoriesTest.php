<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Discount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Models\UserCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserCategoriesTest extends TestCase
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

    private function actingAsManager(): Staff
    {
        $staff = $this->staffWithPermissions(['user_categories.manage']);
        Sanctum::actingAs($staff);

        return $staff;
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $category = UserCategory::factory()->create();
        $user = User::factory()->create();
        $category->users()->attach($user);

        $endpoints = [
            ['getJson', '/api/v1/admin/user-categories'],
            ['postJson', '/api/v1/admin/user-categories'],
            ['getJson', "/api/v1/admin/user-categories/{$category->id}"],
            ['putJson', "/api/v1/admin/user-categories/{$category->id}"],
            ['deleteJson', "/api/v1/admin/user-categories/{$category->id}"],
            ['getJson', "/api/v1/admin/user-categories/{$category->id}/members"],
            ['postJson', "/api/v1/admin/user-categories/{$category->id}/members"],
            ['deleteJson', "/api/v1/admin/user-categories/{$category->id}/members/{$user->id}"],
        ];

        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertUnauthorized();
        }

        Sanctum::actingAs(User::factory()->create());
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }

        Sanctum::actingAs($this->staffWithPermissions(['sports.manage']));
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
    }

    public function test_category_crud_and_audit(): void
    {
        $this->actingAsManager();

        $id = $this->postJson('/api/v1/admin/user-categories', ['name_ar' => 'طلاب', 'name_en' => 'Students', 'description' => 'خصم الطلاب'])
            ->assertCreated()
            ->assertJsonPath('message', __('admin_user_categories.created', [], 'ar'))
            ->assertJsonPath('data.name_ar', 'طلاب')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.users_count', 0)
            ->json('data.id');

        $this->assertTrue(AuditLog::query()->where('action', 'user_category.created')->where('entity_id', $id)->exists());

        $this->getJson("/api/v1/admin/user-categories/{$id}")->assertOk()->assertJsonPath('data.name_en', 'Students');

        $this->putJson("/api/v1/admin/user-categories/{$id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.name_ar', 'طلاب');
        $this->assertTrue(AuditLog::query()->where('action', 'user_category.updated')->where('entity_id', $id)->exists());

        $this->deleteJson("/api/v1/admin/user-categories/{$id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_user_categories.deleted', [], 'ar'));
        $this->assertDatabaseMissing('user_categories', ['id' => $id]);
        $this->assertTrue(AuditLog::query()->where('action', 'user_category.deleted')->where('entity_id', $id)->exists());
    }

    public function test_index_filters_by_active_and_counts_members(): void
    {
        $this->actingAsManager();
        $active = UserCategory::factory()->create(['is_active' => true]);
        $inactive = UserCategory::factory()->create(['is_active' => false]);
        $active->users()->attach(User::factory()->count(2)->create()->pluck('id'));

        $this->getJson('/api/v1/admin/user-categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.users_count', 2)
            ->assertJsonPath('data.1.id', $inactive->id);

        $this->getJson('/api/v1/admin/user-categories?is_active=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inactive->id);

        $this->getJson('/api/v1/admin/user-categories?is_active=maybe')
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_active.0', __('admin_user_categories.validation.is_active_invalid', [], 'ar'));
    }

    public function test_validation_errors_in_both_languages(): void
    {
        $this->actingAsManager();

        $this->postJson('/api/v1/admin/user-categories', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name_ar.0', __('admin_user_categories.validation.name_ar_required', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/user-categories', [
                'name_ar' => 'x',
                'name_en' => str_repeat('a', 101),
                'description' => str_repeat('a', 1001),
                'is_active' => 'maybe',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name_en.0', 'The English name must be text of at most 100 characters.')
            ->assertJsonPath('errors.description.0', 'The description must be text of at most 1000 characters.')
            ->assertJsonPath('errors.is_active.0', 'The active flag must be true or false.');
    }

    public function test_delete_is_blocked_by_members_and_by_discounts(): void
    {
        $this->actingAsManager();
        $withMember = UserCategory::factory()->create();
        $withMember->users()->attach(User::factory()->create());
        $withDiscount = UserCategory::factory()->create();
        Discount::factory()->create()->categories()->attach($withDiscount);

        foreach ([$withMember, $withDiscount] as $category) {
            $this->deleteJson("/api/v1/admin/user-categories/{$category->id}")
                ->assertConflict()
                ->assertJsonPath('message', __('admin_user_categories.category_in_use', [], 'ar'));
            $this->assertDatabaseHas('user_categories', ['id' => $category->id]);
        }

        $this->deleteJson('/api/v1/admin/user-categories/999999')->assertNotFound();
    }

    public function test_members_are_paginated_with_assigned_at(): void
    {
        $this->actingAsManager();
        $category = UserCategory::factory()->create();
        $users = User::factory()->count(3)->create();
        $category->users()->attach($users->pluck('id'), ['assigned_at' => now()]);

        $this->getJson("/api/v1/admin/user-categories/{$category->id}/members?per_page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure(['data' => [['id', 'phone', 'full_name', 'assigned_at']]]);

        $this->getJson("/api/v1/admin/user-categories/{$category->id}/members?per_page=1000")
            ->assertUnprocessable()
            ->assertJsonPath('errors.per_page.0', __('admin_user_categories.validation.per_page_invalid', [], 'ar'));
    }

    public function test_adding_members_attaches_only_new_users_and_records_the_staff(): void
    {
        $staff = $this->actingAsManager();
        $category = UserCategory::factory()->create();
        [$existing, $fresh, $other] = User::factory()->count(3)->create()->all();
        $category->users()->attach($existing);

        $this->postJson("/api/v1/admin/user-categories/{$category->id}/members", ['user_ids' => [$existing->id, $fresh->id, $other->id]])
            ->assertOk()
            ->assertJsonPath('message', __('admin_user_categories.members_added', [], 'ar'))
            ->assertJsonPath('data.attached', 2);

        $this->assertDatabaseHas('user_category_members', ['category_id' => $category->id, 'user_id' => $fresh->id, 'assigned_by_staff_id' => $staff->id]);
        $this->assertDatabaseCount('user_category_members', 3);
        $this->assertTrue(AuditLog::query()->where('action', 'user_category.members_added')->where('entity_id', $category->id)->exists());
    }

    public function test_adding_members_validates_input(): void
    {
        $this->actingAsManager();
        $category = UserCategory::factory()->create();
        $user = User::factory()->create();
        $url = "/api/v1/admin/user-categories/{$category->id}/members";

        $this->postJson($url, [])->assertUnprocessable()
            ->assertJsonPath('errors.user_ids.0', __('admin_user_categories.validation.user_ids_invalid', [], 'ar'));
        $this->postJson($url, ['user_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('user_ids');
        $this->postJson($url, ['user_ids' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors('user_ids');
        $this->postJson($url, ['user_ids' => [$user->id, $user->id]])->assertUnprocessable()
            ->assertJsonValidationErrors('user_ids.0');
        $this->postJson($url, ['user_ids' => [999999]])->assertUnprocessable()
            ->assertJsonValidationErrors('user_ids.0');
        $this->postJson($url, ['user_ids' => range(1, 501)])->assertUnprocessable()->assertJsonValidationErrors('user_ids');
    }

    public function test_removing_a_member(): void
    {
        $this->actingAsManager();
        $category = UserCategory::factory()->create();
        $member = User::factory()->create();
        $stranger = User::factory()->create();
        $category->users()->attach($member);

        $this->deleteJson("/api/v1/admin/user-categories/{$category->id}/members/{$stranger->id}")->assertNotFound();

        $this->deleteJson("/api/v1/admin/user-categories/{$category->id}/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_user_categories.member_removed', [], 'ar'));

        $this->assertDatabaseMissing('user_category_members', ['category_id' => $category->id, 'user_id' => $member->id]);
        $this->assertTrue(AuditLog::query()->where('action', 'user_category.member_removed')->where('entity_id', $category->id)->exists());
    }
}

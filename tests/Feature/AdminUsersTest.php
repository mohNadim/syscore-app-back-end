<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Child;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Models\UserCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUsersTest extends TestCase
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

    private function actingAsMemberManager(): Staff
    {
        $staff = $this->staffWithPermissions(['members.view', 'members.manage']);
        Sanctum::actingAs($staff);

        return $staff;
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $user = User::factory()->create();
        $child = Child::factory()->create(['user_id' => $user->id]);

        $endpoints = [
            ['getJson', '/api/v1/admin/users'],
            ['getJson', "/api/v1/admin/users/{$user->id}"],
            ['putJson', "/api/v1/admin/users/{$user->id}"],
            ['getJson', "/api/v1/admin/users/{$user->id}/children"],
            ['putJson', "/api/v1/admin/users/{$user->id}/children/{$child->id}"],
            ['putJson', "/api/v1/admin/users/{$user->id}/categories"],
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

    public function test_view_permission_cannot_manage_and_manage_permission_cannot_view(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($this->staffWithPermissions(['members.view']));
        $this->getJson('/api/v1/admin/users')->assertOk();
        $this->putJson("/api/v1/admin/users/{$user->id}", ['is_active' => false])->assertForbidden();

        Sanctum::actingAs($this->staffWithPermissions(['members.manage']));
        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->putJson("/api/v1/admin/users/{$user->id}", ['is_active' => false])->assertOk();
    }

    public function test_index_lists_users_with_children_count_and_categories(): void
    {
        $this->actingAsMemberManager();
        $user = User::factory()->create();
        Child::factory()->count(2)->create(['user_id' => $user->id]);
        $category = UserCategory::factory()->create();
        $user->categories()->attach($category->id);

        $this->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonPath('data.0.id', $user->id)
            ->assertJsonPath('data.0.children_count', 2)
            ->assertJsonPath('data.0.categories.0.id', $category->id)
            ->assertJsonPath('data.0.categories.0.name_ar', $category->name_ar)
            ->assertJsonPath('data.0.categories.0.name_en', $category->name_en);
    }

    public function test_index_searches_by_name_or_phone_and_filters(): void
    {
        $this->actingAsMemberManager();
        $ali = User::factory()->create(['full_name' => 'Ali Hassan', 'is_active' => true]);
        $sara = User::factory()->create(['full_name' => 'Sara Omar', 'is_active' => false]);
        $category = UserCategory::factory()->create();
        $sara->categories()->attach($category->id);

        $this->getJson('/api/v1/admin/users?search=Hassan')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ali->id);
        $this->getJson('/api/v1/admin/users?search='.$sara->phone)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $sara->id);
        $this->getJson('/api/v1/admin/users?is_active=0')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $sara->id);
        $this->getJson('/api/v1/admin/users?is_active=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ali->id);
        $this->getJson("/api/v1/admin/users?category_id={$category->id}")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $sara->id);
        $this->getJson('/api/v1/admin/users?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
    }

    public function test_index_rejects_wrong_types_with_localized_messages(): void
    {
        $this->actingAsMemberManager();

        $this->getJson('/api/v1/admin/users?is_active=maybe&category_id=abc&per_page=0')
            ->assertUnprocessable()
            ->assertJsonPath('errors.is_active.0', __('admin_users.validation.is_active_invalid', [], 'ar'))
            ->assertJsonPath('errors.category_id.0', __('admin_users.validation.category_id_invalid', [], 'ar'))
            ->assertJsonPath('errors.per_page.0', __('admin_users.validation.per_page_invalid', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/admin/users?per_page=101&category_id=999999')
            ->assertUnprocessable()
            ->assertJsonPath('errors.per_page.0', __('admin_users.validation.per_page_invalid', [], 'en'))
            ->assertJsonPath('errors.category_id.0', __('admin_users.validation.category_id_invalid', [], 'en'));
    }

    public function test_show_returns_details_with_children_and_categories_and_404(): void
    {
        $this->actingAsMemberManager();
        $user = User::factory()->create();
        $child = Child::factory()->create(['user_id' => $user->id]);
        $category = UserCategory::factory()->create();
        $user->categories()->attach($category->id);

        $this->getJson("/api/v1/admin/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.phone', $user->phone)
            ->assertJsonPath('data.children.0.id', $child->id)
            ->assertJsonPath('data.children.0.is_active', true)
            ->assertJsonPath('data.categories.0.id', $category->id);

        $this->getJson('/api/v1/admin/users/999999')->assertNotFound();
    }

    public function test_update_edits_profile_and_audits(): void
    {
        $this->actingAsMemberManager();
        $user = User::factory()->create(['preferred_lang' => 'ar']);

        $this->putJson("/api/v1/admin/users/{$user->id}", ['full_name' => 'اسم جديد', 'preferred_lang' => 'en'])
            ->assertOk()
            ->assertJsonPath('message', __('admin_users.updated', [], 'ar'))
            ->assertJsonPath('data.full_name', 'اسم جديد')
            ->assertJsonPath('data.preferred_lang', 'en');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'full_name' => 'اسم جديد', 'preferred_lang' => 'en']);
        $this->assertTrue(AuditLog::query()->where('action', 'user.updated')->where('entity_id', $user->id)->exists());
    }

    public function test_update_ignores_phone_and_rejects_invalid_values(): void
    {
        $this->actingAsMemberManager();
        $user = User::factory()->create();
        $phone = $user->phone;

        $this->putJson("/api/v1/admin/users/{$user->id}", ['phone' => '0999999999', 'full_name' => 'Valid Name'])->assertOk();
        $this->assertSame($phone, $user->fresh()->phone);

        $this->putJson("/api/v1/admin/users/{$user->id}", ['full_name' => '', 'preferred_lang' => 'fr', 'is_active' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.full_name.0', __('profile.validation.full_name_required', [], 'ar'))
            ->assertJsonPath('errors.preferred_lang.0', __('profile.validation.preferred_lang_invalid', [], 'ar'))
            ->assertJsonPath('errors.is_active.0', __('admin_users.validation.is_active_invalid', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->putJson("/api/v1/admin/users/{$user->id}", ['full_name' => ['a'], 'is_active' => null])
            ->assertUnprocessable()
            ->assertJsonPath('errors.full_name.0', __('profile.validation.full_name_invalid', [], 'en'))
            ->assertJsonPath('errors.is_active.0', __('admin_users.validation.is_active_invalid', [], 'en'));

        $this->putJson('/api/v1/admin/users/999999', ['is_active' => true])->assertNotFound();
    }

    public function test_deactivating_revokes_tokens_but_other_updates_do_not(): void
    {
        $this->actingAsMemberManager();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->createToken('mobile');
        $other->createToken('mobile');

        $this->putJson("/api/v1/admin/users/{$user->id}", ['full_name' => 'Still Active'])->assertOk();
        $this->assertSame(1, $user->tokens()->count());

        $this->putJson("/api/v1/admin/users/{$user->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, $other->tokens()->count());
        $this->assertFalse($user->fresh()->is_active);

        $this->putJson("/api/v1/admin/users/{$user->id}", ['is_active' => true])->assertOk();
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_children_list_includes_archived_and_is_scoped_to_user(): void
    {
        $this->actingAsMemberManager();
        $user = User::factory()->create();
        $active = Child::factory()->create(['user_id' => $user->id]);
        $archived = Child::factory()->create(['user_id' => $user->id, 'is_active' => false]);
        Child::factory()->create();

        $response = $this->getJson("/api/v1/admin/users/{$user->id}/children")->assertOk()->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing([$active->id, $archived->id], collect($response->json('data'))->pluck('id')->all());
        $this->getJson('/api/v1/admin/users/999999/children')->assertNotFound();
    }

    public function test_update_child_edits_fields_and_audits(): void
    {
        $this->actingAsMemberManager();
        $user = User::factory()->create();
        $child = Child::factory()->create(['user_id' => $user->id, 'gender' => 'male']);

        $this->putJson("/api/v1/admin/users/{$user->id}/children/{$child->id}", [
            'full_name' => 'طفل معدل',
            'birth_date' => now()->subYears(8)->toDateString(),
            'gender' => 'female',
            'health_notes' => 'Peanut allergy',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('message', __('admin_users.child_updated', [], 'ar'))
            ->assertJsonPath('data.full_name', 'طفل معدل')
            ->assertJsonPath('data.gender', 'female')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('children', ['id' => $child->id, 'gender' => 'female', 'health_notes' => 'Peanut allergy', 'is_active' => false]);
        $this->assertTrue(AuditLog::query()->where('action', 'child.updated')->where('entity_id', $child->id)->exists());
    }

    public function test_update_child_validation_messages_in_both_languages(): void
    {
        $this->actingAsMemberManager();
        $user = User::factory()->create();
        $child = Child::factory()->create(['user_id' => $user->id]);
        $url = "/api/v1/admin/users/{$user->id}/children/{$child->id}";

        $this->putJson($url, ['full_name' => '', 'gender' => 'other', 'birth_date' => 'yesterday', 'is_active' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.full_name.0', __('children.validation.full_name_required', [], 'ar'))
            ->assertJsonPath('errors.gender.0', __('children.validation.gender_invalid', [], 'ar'))
            ->assertJsonPath('errors.birth_date.0', __('children.validation.birth_date_format', [], 'ar'))
            ->assertJsonPath('errors.is_active.0', __('admin_users.validation.is_active_invalid', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->putJson($url, ['birth_date' => now()->addDay()->toDateString(), 'health_notes' => str_repeat('a', 1001)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.birth_date.0', __('children.validation.birth_date_range', [], 'en'))
            ->assertJsonPath('errors.health_notes.0', __('children.validation.health_notes_invalid', [], 'en'));
    }

    public function test_update_child_under_wrong_user_or_missing_returns_404(): void
    {
        $this->actingAsMemberManager();
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $child = Child::factory()->create(['user_id' => $owner->id, 'full_name' => 'Original']);

        $this->putJson("/api/v1/admin/users/{$stranger->id}/children/{$child->id}", ['full_name' => 'Hacked'])->assertNotFound();
        $this->assertSame('Original', $child->fresh()->full_name);

        $this->putJson("/api/v1/admin/users/{$owner->id}/children/999999", ['full_name' => 'Nope'])->assertNotFound();
    }

    public function test_sync_categories_keeps_existing_metadata_and_detaches_others(): void
    {
        $staff = $this->actingAsMemberManager();
        $original = Staff::factory()->create(['role_id' => $staff->role_id]);
        $user = User::factory()->create();
        [$kept, $removed, $added] = UserCategory::factory()->count(3)->create();
        $user->categories()->attach([
            $kept->id => ['assigned_by_staff_id' => $original->id, 'assigned_at' => '2026-01-01 08:00:00'],
            $removed->id => ['assigned_by_staff_id' => $original->id, 'assigned_at' => '2026-01-01 08:00:00'],
        ]);

        $this->putJson("/api/v1/admin/users/{$user->id}/categories", ['category_ids' => [$kept->id, $added->id]])
            ->assertOk()
            ->assertJsonPath('message', __('admin_users.categories_synced', [], 'ar'))
            ->assertJsonCount(2, 'data.categories');

        $keptRow = DB::table('user_category_members')->where('user_id', $user->id)->where('category_id', $kept->id)->first();
        $addedRow = DB::table('user_category_members')->where('user_id', $user->id)->where('category_id', $added->id)->first();

        $this->assertSame($original->id, (int) $keptRow->assigned_by_staff_id);
        $this->assertSame('2026-01-01 08:00:00', $keptRow->assigned_at);
        $this->assertSame($staff->id, (int) $addedRow->assigned_by_staff_id);
        $this->assertNotNull($addedRow->assigned_at);
        $this->assertDatabaseMissing('user_category_members', ['user_id' => $user->id, 'category_id' => $removed->id]);
        $this->assertTrue(AuditLog::query()->where('action', 'user.categories_synced')->where('entity_id', $user->id)->exists());
    }

    public function test_sync_categories_with_empty_list_clears_all(): void
    {
        $this->actingAsMemberManager();
        $user = User::factory()->create();
        $user->categories()->attach(UserCategory::factory()->create()->id);

        $this->putJson("/api/v1/admin/users/{$user->id}/categories", ['category_ids' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data.categories');

        $this->assertSame(0, $user->categories()->count());
    }

    public function test_sync_categories_validation_in_both_languages(): void
    {
        $this->actingAsMemberManager();
        $user = User::factory()->create();
        $url = "/api/v1/admin/users/{$user->id}/categories";

        $this->putJson($url, [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.category_ids.0', __('admin_users.validation.category_ids_invalid', [], 'ar'));

        $this->putJson($url, ['category_ids' => 'abc'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.category_ids.0', __('admin_users.validation.category_ids_invalid', [], 'ar'));

        $invalid = __('admin_users.validation.category_id_invalid', [], 'en');
        $errors = $this->withHeader('Accept-Language', 'en')
            ->putJson($url, ['category_ids' => ['x', 999999]])
            ->assertUnprocessable()
            ->json('errors');
        $this->assertSame($invalid, $errors['category_ids.0'][0]);
        $this->assertSame($invalid, $errors['category_ids.1'][0]);

        $category = UserCategory::factory()->create();
        $errors = $this->withHeader('Accept-Language', 'en')
            ->putJson($url, ['category_ids' => [$category->id, $category->id]])
            ->assertUnprocessable()
            ->json('errors');
        $this->assertSame(__('admin_users.validation.category_id_duplicate', [], 'en'), $errors['category_ids.0'][0]);

        $this->putJson('/api/v1/admin/users/999999/categories', ['category_ids' => []])->assertNotFound();
    }
}

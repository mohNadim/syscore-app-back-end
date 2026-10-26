<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStaffRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');
    }

    /**
     * Create a staff member whose role holds the given permission codes.
     *
     * @param  list<string>  $codes
     * @param  array<string, mixed>  $attributes
     */
    private function staffWithPermissions(array $codes, array $attributes = []): Staff
    {
        $role = Role::factory()->create(['name_ar' => 'دور '.fake()->unique()->numerify('####'), 'name_en' => 'Role']);

        foreach ($codes as $code) {
            $permission = Permission::query()->firstOrCreate(['code' => $code], ['description' => $code]);
            $role->permissions()->attach($permission);
        }

        return Staff::factory()->create(['role_id' => $role->id, ...$attributes]);
    }

    private function permission(string $code): Permission
    {
        return Permission::query()->firstOrCreate(['code' => $code], ['description' => $code]);
    }

    private function actingAsRolesManager(): Staff
    {
        $staff = $this->staffWithPermissions(['roles.manage']);
        Sanctum::actingAs($staff);

        return $staff;
    }

    private function actingAsStaffManager(): Staff
    {
        $staff = $this->staffWithPermissions(['staff.manage']);
        Sanctum::actingAs($staff);

        return $staff;
    }

    /**
     * @return array<string, mixed>
     */
    private function staffPayload(array $overrides = []): array
    {
        return [
            'full_name' => 'Sami Ali',
            'username' => 'sami.ali',
            'phone' => '+963911111111',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
            'role_id' => Role::factory()->create()->id,
            ...$overrides,
        ];
    }

    public function test_access_control_for_every_endpoint(): void
    {
        $role = Role::factory()->create();
        $other = Staff::factory()->create();
        $endpoints = [
            ['getJson', '/api/v1/admin/permissions'],
            ['getJson', '/api/v1/admin/roles'],
            ['getJson', "/api/v1/admin/roles/{$role->id}"],
            ['getJson', '/api/v1/admin/staff'],
            ['getJson', "/api/v1/admin/staff/{$other->id}"],
        ];

        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertUnauthorized();
        }

        Sanctum::actingAs(User::factory()->create());
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }

        Sanctum::actingAs($this->staffWithPermissions(['bookings.view']));
        foreach ($endpoints as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }

        Sanctum::actingAs($this->staffWithPermissions(['staff.manage']));
        $this->getJson('/api/v1/admin/roles')->assertForbidden();
        Sanctum::actingAs($this->staffWithPermissions(['roles.manage']));
        $this->getJson('/api/v1/admin/staff')->assertForbidden();
    }

    public function test_permissions_are_listed_ordered_by_code(): void
    {
        $this->actingAsRolesManager();
        $this->permission('zzz.last');
        $this->permission('aaa.first');

        $codes = $this->getJson('/api/v1/admin/permissions')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'code', 'description']]])
            ->json('data.*.code');

        $this->assertSame($codes, collect($codes)->sort()->values()->all());
        $this->assertSame('aaa.first', $codes[0]);
    }

    public function test_roles_index_and_show_include_permissions_and_staff_count(): void
    {
        $this->actingAsRolesManager();
        $role = Role::factory()->create();
        $role->permissions()->attach($this->permission('bookings.view'));
        Staff::factory()->count(2)->create(['role_id' => $role->id]);

        $this->getJson('/api/v1/admin/roles')->assertOk()
            ->assertJsonFragment(['id' => $role->id, 'staff_count' => 2]);

        $this->getJson("/api/v1/admin/roles/{$role->id}")->assertOk()
            ->assertJsonPath('data.staff_count', 2)
            ->assertJsonPath('data.permissions.0.code', 'bookings.view')
            ->assertJsonStructure(['data' => ['name_ar', 'name_en', 'is_system']]);

        $this->getJson('/api/v1/admin/roles/999999')->assertNotFound();
    }

    public function test_role_can_be_created_with_permissions_and_is_audited(): void
    {
        $this->actingAsRolesManager();
        $permission = $this->permission('bookings.view');

        $this->postJson('/api/v1/admin/roles', [
            'name_ar' => 'مشرف',
            'name_en' => 'Supervisor',
            'permission_ids' => [$permission->id],
        ])
            ->assertCreated()
            ->assertJsonPath('message', __('admin_roles.created', [], 'ar'))
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.permissions.0.code', 'bookings.view');

        $role = Role::query()->where('name_ar', 'مشرف')->sole();
        $this->assertFalse($role->is_system);
        $this->assertSame(1, $role->permissions()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.created', 'entity_id' => $role->id]);
    }

    public function test_role_can_be_created_with_empty_permissions(): void
    {
        $this->actingAsRolesManager();

        $this->postJson('/api/v1/admin/roles', ['name_ar' => 'بلا صلاحيات', 'permission_ids' => []])
            ->assertCreated();
    }

    public function test_role_validation_errors_in_both_languages(): void
    {
        $this->actingAsRolesManager();
        Role::factory()->create(['name_ar' => 'مكرر']);

        $this->postJson('/api/v1/admin/roles', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name_ar', 'permission_ids'])
            ->assertJsonPath('errors.name_ar.0', __('admin_roles.validation.name_ar_required', [], 'ar'))
            ->assertJsonPath('errors.permission_ids.0', __('admin_roles.validation.permission_ids_required', [], 'ar'));

        $this->postJson('/api/v1/admin/roles', ['name_ar' => 'مكرر', 'permission_ids' => []])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name_ar.0', __('admin_roles.validation.name_ar_taken', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/roles', ['name_ar' => 'مكرر', 'permission_ids' => []])
            ->assertJsonPath('errors.name_ar.0', 'A role with this Arabic name already exists.');

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/roles', ['name_ar' => ['x'], 'name_en' => str_repeat('a', 101), 'permission_ids' => 'abc'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name_ar.0', 'The Arabic role name must be text of at most 100 characters.')
            ->assertJsonPath('errors.name_en.0', 'The English role name must be text of at most 100 characters.')
            ->assertJsonPath('errors.permission_ids.0', 'The permissions must be a list of permission ids.');

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/roles', ['name_ar' => 'جديد', 'permission_ids' => [999999, 'x']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permission_ids.0', 'permission_ids.1']);

        $id = $this->permission('bookings.view')->id;
        $this->postJson('/api/v1/admin/roles', ['name_ar' => 'جديد', 'permission_ids' => [$id, $id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['permission_ids.0']);
    }

    public function test_role_update_changes_fields_and_permissions_with_audit(): void
    {
        $this->actingAsRolesManager();
        $role = Role::factory()->create(['name_ar' => 'قديم']);
        $role->permissions()->attach($this->permission('bookings.view'));
        $new = $this->permission('payments.view');

        $this->putJson("/api/v1/admin/roles/{$role->id}", ['name_ar' => 'جديد', 'permission_ids' => [$new->id]])
            ->assertOk()
            ->assertJsonPath('message', __('admin_roles.updated', [], 'ar'))
            ->assertJsonPath('data.name_ar', 'جديد')
            ->assertJsonPath('data.permissions.0.code', 'payments.view');

        $this->assertDatabaseHas('audit_logs', ['action' => 'role.updated', 'entity_id' => $role->id]);
        $log = AuditLog::query()->where('action', 'role.permissions_updated')->sole();
        $this->assertSame(['bookings.view'], $log->before['permissions']);
        $this->assertSame(['payments.view'], $log->after['permissions']);
    }

    public function test_role_update_is_partial_and_may_keep_its_own_name(): void
    {
        $this->actingAsRolesManager();
        $role = Role::factory()->create(['name_ar' => 'ثابت', 'name_en' => 'Old']);

        $this->patchJson("/api/v1/admin/roles/{$role->id}", ['name_ar' => 'ثابت', 'name_en' => 'New'])
            ->assertOk()
            ->assertJsonPath('data.name_en', 'New');

        $other = Role::factory()->create(['name_ar' => 'آخر']);
        $this->patchJson("/api/v1/admin/roles/{$role->id}", ['name_ar' => 'آخر'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name_ar');

        $this->patchJson('/api/v1/admin/roles/999999', ['name_en' => 'x'])->assertNotFound();
        $this->assertNotNull($other);
    }

    public function test_staff_cannot_remove_roles_manage_from_their_own_role(): void
    {
        $staff = $this->actingAsRolesManager();
        $other = $this->permission('bookings.view');

        $this->putJson("/api/v1/admin/roles/{$staff->role_id}", ['permission_ids' => [$other->id]])
            ->assertConflict()
            ->assertJsonPath('message', __('admin_roles.cannot_remove_own_roles_permission', [], 'ar'));

        $this->assertTrue($staff->role->permissions()->where('code', 'roles.manage')->exists());

        $keep = $this->permission('roles.manage');
        $this->putJson("/api/v1/admin/roles/{$staff->role_id}", ['permission_ids' => [$keep->id, $other->id]])
            ->assertOk();
    }

    public function test_role_destroy_rules(): void
    {
        $this->actingAsRolesManager();

        $system = Role::factory()->create(['is_system' => true]);
        $this->deleteJson("/api/v1/admin/roles/{$system->id}")
            ->assertConflict()
            ->assertJsonPath('message', __('admin_roles.system_role', [], 'ar'));

        $used = Role::factory()->create();
        Staff::factory()->create(['role_id' => $used->id]);
        $this->deleteJson("/api/v1/admin/roles/{$used->id}")
            ->assertConflict()
            ->assertJsonPath('message', __('admin_roles.in_use', [], 'ar'));

        $free = Role::factory()->create();
        $free->permissions()->attach($this->permission('bookings.view'));
        $this->deleteJson("/api/v1/admin/roles/{$free->id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_roles.deleted', [], 'ar'));

        $this->assertModelMissing($free);
        $this->assertDatabaseMissing('role_permissions', ['role_id' => $free->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.deleted', 'entity_id' => $free->id]);

        $this->deleteJson('/api/v1/admin/roles/999999')->assertNotFound();
    }

    public function test_staff_index_is_paginated_and_filterable(): void
    {
        $this->actingAsStaffManager();
        $role = Role::factory()->create();
        Staff::factory()->count(16)->create(['role_id' => $role->id, 'full_name' => 'Zed Worker']);
        Staff::factory()->create(['full_name' => 'Amina Search', 'username' => 'amina', 'is_active' => false]);

        $this->getJson('/api/v1/admin/staff')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 18)
            ->assertJsonStructure(['data' => [['id', 'role' => ['id']]], 'links', 'meta']);

        $this->getJson('/api/v1/admin/staff?per_page=5&page=2')->assertJsonCount(5, 'data');
        $this->getJson('/api/v1/admin/staff?search=amin')->assertJsonCount(1, 'data')->assertJsonPath('data.0.username', 'amina');
        $this->getJson('/api/v1/admin/staff?search=amina')->assertJsonPath('meta.total', 1);
        $this->getJson("/api/v1/admin/staff?role_id={$role->id}")->assertJsonPath('meta.total', 16);
        $this->getJson('/api/v1/admin/staff?is_active=0')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/admin/staff?is_active=1')->assertJsonPath('meta.total', 17);

        $this->withHeader('Accept-Language', 'en')->getJson('/api/v1/admin/staff?per_page=101')
            ->assertUnprocessable()
            ->assertJsonPath('errors.per_page.0', 'The page size must be a number between 1 and 100.');
        $this->getJson('/api/v1/admin/staff?role_id=999999')->assertUnprocessable()->assertJsonValidationErrors('role_id');
        $this->getJson('/api/v1/admin/staff?is_active=maybe')->assertUnprocessable()->assertJsonValidationErrors('is_active');
    }

    public function test_staff_json_never_exposes_password_hash(): void
    {
        $this->actingAsStaffManager();
        $staff = Staff::factory()->create();

        $this->getJson('/api/v1/admin/staff')->assertOk()->assertDontSee('password_hash');
        $this->getJson("/api/v1/admin/staff/{$staff->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.password_hash')
            ->assertJsonStructure(['data' => ['id', 'full_name', 'username', 'role', 'permissions']]);
        $this->getJson('/api/v1/admin/staff/999999')->assertNotFound();
    }

    public function test_staff_can_be_created_and_log_in(): void
    {
        $this->actingAsStaffManager();
        $payload = $this->staffPayload();

        $this->postJson('/api/v1/admin/staff', $payload)
            ->assertCreated()
            ->assertJsonPath('message', __('admin_staff.created', [], 'ar'))
            ->assertJsonPath('data.username', 'sami.ali')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.role.id', $payload['role_id'])
            ->assertJsonMissingPath('data.password_hash');

        $staff = Staff::query()->where('username', 'sami.ali')->sole();
        $this->assertNotSame('secret-pass', $staff->password_hash);
        $log = AuditLog::query()->where('action', 'staff.created')->sole();
        $this->assertArrayNotHasKey('password_hash', $log->after);

        $this->postJson('/api/v1/admin/auth/login', ['username' => 'sami.ali', 'password' => 'secret-pass'])->assertOk();
    }

    public function test_staff_store_validation_errors(): void
    {
        $this->actingAsStaffManager();
        Staff::factory()->create(['username' => 'taken']);

        $this->postJson('/api/v1/admin/staff', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.full_name.0', __('admin_staff.validation.full_name_required', [], 'ar'))
            ->assertJsonValidationErrors(['full_name', 'username', 'password', 'role_id']);

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/staff', $this->staffPayload(['username' => 'taken']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', 'This username is already taken.');

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/staff', $this->staffPayload(['password_confirmation' => 'different']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'The password confirmation does not match.');

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/staff', $this->staffPayload(['username' => 'bad name!', 'password' => 'short', 'password_confirmation' => 'short']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', 'The username may only contain letters, numbers, dots, underscores and hyphens.')
            ->assertJsonPath('errors.password.0', 'The password must be at least 8 characters.');

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/staff', $this->staffPayload(['full_name' => 'A', 'username' => 'ab', 'role_id' => 999999, 'is_active' => 'nope', 'phone' => str_repeat('1', 21)]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.full_name.0', 'The full name must be between 2 and 150 characters.')
            ->assertJsonPath('errors.username.0', 'The username must be between 3 and 100 characters.')
            ->assertJsonPath('errors.role_id.0', 'The role must be a valid role id.')
            ->assertJsonPath('errors.is_active.0', 'The active flag must be true or false.')
            ->assertJsonPath('errors.phone.0', 'The phone number must be text of at most 20 characters.');

        $this->postJson('/api/v1/admin/staff', $this->staffPayload(['full_name' => ['x'], 'role_id' => 'abc']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['full_name', 'role_id']);
    }

    public function test_staff_update_changes_fields_and_password_revokes_tokens(): void
    {
        $this->actingAsStaffManager();
        $target = Staff::factory()->create(['username' => 'target']);
        $target->createToken('one');
        $newRole = Role::factory()->create();

        $this->putJson("/api/v1/admin/staff/{$target->id}", [
            'full_name' => 'Renamed Person',
            'role_id' => $newRole->id,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])
            ->assertOk()
            ->assertJsonPath('message', __('admin_staff.updated', [], 'ar'))
            ->assertJsonPath('data.full_name', 'Renamed Person')
            ->assertJsonPath('data.role.id', $newRole->id);

        $this->assertSame(0, $target->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.updated', 'entity_id' => $target->id]);
        $this->postJson('/api/v1/admin/auth/login', ['username' => 'target', 'password' => 'brand-new-pass'])->assertOk();

        $this->patchJson("/api/v1/admin/staff/{$target->id}", ['username' => 'target'])->assertOk();
        $this->patchJson("/api/v1/admin/staff/{$target->id}", ['password' => 'brand-new-pass'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
        $this->patchJson('/api/v1/admin/staff/999999', ['full_name' => 'Nobody'])->assertNotFound();

        Staff::factory()->create(['username' => 'someone']);
        $this->patchJson("/api/v1/admin/staff/{$target->id}", ['username' => 'someone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    }

    public function test_deactivating_staff_through_update_revokes_their_tokens(): void
    {
        $this->actingAsStaffManager();
        $target = Staff::factory()->create();
        $target->createToken('one');
        $target->createToken('two');

        $this->patchJson("/api/v1/admin/staff/{$target->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(0, $target->tokens()->count());
    }

    public function test_staff_cannot_deactivate_themselves_or_change_their_own_role(): void
    {
        $staff = $this->actingAsStaffManager();
        $otherRole = Role::factory()->create();

        $this->patchJson("/api/v1/admin/staff/{$staff->id}", ['is_active' => false])
            ->assertConflict()
            ->assertJsonPath('message', __('admin_staff.cannot_modify_self', [], 'ar'));

        $this->patchJson("/api/v1/admin/staff/{$staff->id}", ['role_id' => $otherRole->id])
            ->assertConflict();

        $this->deleteJson("/api/v1/admin/staff/{$staff->id}")
            ->assertConflict()
            ->assertJsonPath('message', __('admin_staff.cannot_deactivate_self', [], 'ar'));

        $this->assertTrue($staff->fresh()->is_active);
    }

    public function test_staff_changing_own_password_keeps_the_current_token(): void
    {
        $staff = $this->staffWithPermissions(['staff.manage']);
        $staff->createToken('other-device');
        $token = $staff->createToken('current');

        $this->withToken($token->plainTextToken)
            ->patchJson("/api/v1/admin/staff/{$staff->id}", [
                'full_name' => 'Self Edit',
                'role_id' => $staff->role_id,
                'password' => 'another-pass1',
                'password_confirmation' => 'another-pass1',
            ])
            ->assertOk();

        $this->assertSame(1, $staff->tokens()->count());
        $this->assertSame($token->accessToken->id, $staff->tokens()->sole()->id);
    }

    public function test_destroy_deactivates_staff_and_revokes_tokens(): void
    {
        $this->actingAsStaffManager();
        $target = Staff::factory()->create();
        $target->createToken('one');
        $target->createToken('two');

        $this->deleteJson("/api/v1/admin/staff/{$target->id}")
            ->assertOk()
            ->assertJsonPath('message', __('admin_staff.deactivated', [], 'ar'));

        $this->assertDatabaseHas('staff', ['id' => $target->id, 'is_active' => false]);
        $this->assertSame(0, $target->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.deactivated', 'entity_id' => $target->id]);

        $this->deleteJson('/api/v1/admin/staff/999999')->assertNotFound();
    }
}

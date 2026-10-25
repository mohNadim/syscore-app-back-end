<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthTest extends TestCase
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
        $role = Role::factory()->create(['name_ar' => 'موظف استقبال', 'name_en' => 'Front desk']);

        foreach ($codes as $code) {
            $role->permissions()->attach(Permission::factory()->create(['code' => $code]));
        }

        return Staff::factory()->create(['role_id' => $role->id, ...$attributes]);
    }

    public function test_staff_can_log_in_and_receives_role_and_permissions(): void
    {
        $staff = $this->staffWithPermissions(['bookings.view', 'payments.view'], ['username' => 'reception']);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'username' => 'reception',
            'password' => 'password',
            'device_name' => 'Front desk PC',
        ])
            ->assertOk()
            ->assertJsonPath('message', __('admin_auth.logged_in', [], 'ar'))
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.staff.id', $staff->id)
            ->assertJsonPath('data.staff.username', 'reception')
            ->assertJsonPath('data.staff.role.name_ar', 'موظف استقبال')
            ->assertJsonPath('data.staff.role.name_en', 'Front desk')
            ->assertJsonPath('data.staff.permissions', ['bookings.view', 'payments.view'])
            ->assertJsonMissingPath('data.staff.password_hash');

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotNull($staff->fresh()->last_login_at);
        $this->assertSame('Front desk PC', $staff->tokens()->sole()->name);
    }

    public function test_token_from_login_works_on_admin_routes(): void
    {
        $this->staffWithPermissions([], ['username' => 'manager']);

        $token = $this->postJson('/api/v1/admin/auth/login', ['username' => 'manager', 'password' => 'password'])
            ->json('data.token');

        $this->withToken($token)->getJson('/api/v1/admin/me')
            ->assertOk()
            ->assertJsonPath('data.username', 'manager');
    }

    public function test_wrong_password_and_unknown_username_return_the_same_error(): void
    {
        $this->staffWithPermissions([], ['username' => 'reception']);

        $wrongPassword = $this->postJson('/api/v1/admin/auth/login', ['username' => 'reception', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', __('admin_auth.failed', [], 'ar'));

        $unknownUser = $this->postJson('/api/v1/admin/auth/login', ['username' => 'nobody', 'password' => 'password'])
            ->assertUnprocessable();

        $this->assertSame($wrongPassword->json(), $unknownUser->json());
    }

    public function test_disabled_staff_cannot_log_in(): void
    {
        $this->staffWithPermissions([], ['username' => 'old', 'is_active' => false]);

        $this->postJson('/api/v1/admin/auth/login', ['username' => 'old', 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('message', __('admin_auth.account_disabled', [], 'ar'));

        $this->assertSame(0, Staff::query()->sole()->tokens()->count());
    }

    public function test_login_validates_input_in_both_languages(): void
    {
        $this->postJson('/api/v1/admin/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', __('admin_auth.validation.username_required', [], 'ar'))
            ->assertJsonPath('errors.password.0', __('admin_auth.validation.password_required', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/admin/auth/login', ['username' => ['x'], 'password' => 123, 'device_name' => str_repeat('a', 101)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.username.0', 'The username must be text of at most 100 characters.')
            ->assertJsonPath('errors.password.0', 'The password must be text of at most 255 characters.')
            ->assertJsonPath('errors.device_name.0', 'The device name must be text of at most 100 characters.');
    }

    public function test_login_is_throttled_per_username(): void
    {
        $this->staffWithPermissions([], ['username' => 'reception']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/admin/auth/login', ['username' => 'reception', 'password' => 'wrong'])->assertUnprocessable();
        }

        $this->postJson('/api/v1/admin/auth/login', ['username' => 'RECEPTION', 'password' => 'password'])
            ->assertTooManyRequests()
            ->assertJsonPath('message', __('api.too_many_requests', [], 'ar'));
    }

    public function test_me_returns_staff_with_role_and_permissions(): void
    {
        $staff = Sanctum::actingAs($this->staffWithPermissions(['members.view']));

        $this->getJson('/api/v1/admin/me')
            ->assertOk()
            ->assertExactJsonStructure(['data' => [
                'id', 'full_name', 'username', 'phone', 'is_active', 'last_login_at', 'created_at',
                'role' => ['id', 'name_ar', 'name_en', 'is_system'],
                'permissions',
            ]])
            ->assertJsonPath('data.id', $staff->id)
            ->assertJsonPath('data.permissions', ['members.view']);
    }

    public function test_logout_revokes_current_token(): void
    {
        $staff = $this->staffWithPermissions([]);
        $token = $staff->createToken('admin')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/admin/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', __('admin_auth.logged_out', [], 'ar'));

        $this->assertSame(0, $staff->tokens()->count());
    }

    public function test_admin_routes_require_staff_token(): void
    {
        $this->getJson('/api/v1/admin/me')->assertUnauthorized();
        $this->postJson('/api/v1/admin/auth/logout')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/admin/me')->assertForbidden();
        $this->postJson('/api/v1/admin/auth/logout')->assertForbidden();
    }

    public function test_disabled_staff_token_is_rejected(): void
    {
        Sanctum::actingAs($this->staffWithPermissions([], ['is_active' => false]));

        $this->getJson('/api/v1/admin/me')->assertForbidden();
    }
}

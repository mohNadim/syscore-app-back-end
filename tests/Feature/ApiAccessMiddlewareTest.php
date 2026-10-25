<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAccessMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');

        Route::middleware(['api', 'auth:sanctum', 'user'])->get('api/v1/_test/user', fn () => response()->json(['ok' => true]));
        Route::middleware(['api', 'auth:sanctum', 'staff'])->get('api/v1/_test/staff', fn () => response()->json(['ok' => true]));
        Route::middleware(['api', 'auth:sanctum', 'permission:bookings.manage,payments.manage'])
            ->get('api/v1/_test/permission', fn () => response()->json(['ok' => true]));
    }

    /**
     * Create an active staff member whose role holds the given permission codes.
     *
     * @param  list<string>  $codes
     */
    private function staffWithPermissions(array $codes): Staff
    {
        $role = Role::factory()->create();

        foreach ($codes as $code) {
            $role->permissions()->attach(Permission::factory()->create(['code' => $code]));
        }

        return Staff::factory()->create(['role_id' => $role->id]);
    }

    public function test_active_user_can_reach_user_route(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/_test/user')->assertOk();
    }

    public function test_staff_token_is_rejected_on_user_route(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $this->getJson('/api/v1/_test/user')->assertForbidden();
    }

    public function test_user_token_is_rejected_on_staff_route(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/_test/staff')
            ->assertForbidden()
            ->assertExactJson(['message' => trans('api.forbidden', [], 'ar')]);
    }

    public function test_inactive_user_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->inactive()->create());

        $this->getJson('/api/v1/_test/user')->assertForbidden();
    }

    public function test_inactive_staff_is_rejected(): void
    {
        Sanctum::actingAs(Staff::factory()->inactive()->create());

        $this->getJson('/api/v1/_test/staff')->assertForbidden();
    }

    public function test_active_staff_can_reach_staff_route(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $this->getJson('/api/v1/_test/staff')->assertOk();
    }

    public function test_staff_without_permission_is_rejected(): void
    {
        Sanctum::actingAs($this->staffWithPermissions(['other.manage']));

        $this->getJson('/api/v1/_test/permission')->assertForbidden();
    }

    public function test_staff_with_one_of_the_permissions_is_allowed(): void
    {
        Sanctum::actingAs($this->staffWithPermissions(['payments.manage']));

        $this->getJson('/api/v1/_test/permission')->assertOk();
    }

    public function test_inactive_staff_with_permission_is_rejected(): void
    {
        $staff = $this->staffWithPermissions(['bookings.manage']);
        $staff->update(['is_active' => false]);
        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/_test/permission')->assertForbidden();
    }

    public function test_user_token_is_rejected_on_permission_route(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/_test/permission')->assertForbidden();
    }

    public function test_has_permission_helper(): void
    {
        $staff = $this->staffWithPermissions(['bookings.manage']);

        $this->assertTrue($staff->hasPermission('bookings.manage'));
        $this->assertFalse($staff->hasPermission('staff.manage'));
    }
}

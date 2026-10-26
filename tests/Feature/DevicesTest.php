<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DevicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');
    }

    public function test_user_can_register_a_device(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/devices', ['platform' => 'android', 'push_token' => 'token-1', 'app_version' => '1.2.0'])
            ->assertCreated()
            ->assertJsonPath('message', __('devices.registered', [], 'ar'))
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.app_version', '1.2.0')
            ->assertJsonMissingPath('data.push_token');

        $device = UserDevice::query()->sole();
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame('token-1', $device->push_token);
        $this->assertNotNull($device->last_seen_at);
    }

    public function test_registering_the_same_token_again_updates_it(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        UserDevice::factory()->for($user)->create(['push_token' => 'token-1', 'platform' => 'android', 'app_version' => '1.0.0']);

        $this->postJson('/api/v1/devices', ['platform' => 'android', 'push_token' => 'token-1', 'app_version' => '1.1.0'])
            ->assertOk()
            ->assertJsonPath('data.app_version', '1.1.0');

        $this->assertSame(1, UserDevice::query()->count());
        $this->assertNotNull(UserDevice::query()->sole()->last_seen_at);
    }

    public function test_token_of_another_account_moves_to_the_current_user(): void
    {
        $previousOwner = User::factory()->create();
        UserDevice::factory()->for($previousOwner)->create(['push_token' => 'shared-phone']);
        $user = Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/devices', ['platform' => 'ios', 'push_token' => 'shared-phone'])->assertOk();

        $this->assertSame($user->id, UserDevice::query()->sole()->user_id);
        $this->assertSame(0, $previousOwner->devices()->count());
    }

    public function test_store_validates_input_in_both_languages(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/devices', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.platform.0', __('devices.validation.platform_required', [], 'ar'))
            ->assertJsonPath('errors.push_token.0', __('devices.validation.push_token_required', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/devices', ['platform' => 'windows', 'push_token' => str_repeat('a', 501), 'app_version' => ['1']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.platform.0', 'The platform must be android or ios.')
            ->assertJsonPath('errors.push_token.0', 'The push token must be text of at most 500 characters.')
            ->assertJsonPath('errors.app_version.0', 'The app version must be text of at most 20 characters.');

        $this->assertSame(0, UserDevice::query()->count());
    }

    public function test_user_can_remove_own_device(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $device = UserDevice::factory()->for($user)->create(['push_token' => 'token-1']);

        $this->deleteJson('/api/v1/devices', ['push_token' => 'token-1'])
            ->assertOk()
            ->assertJsonPath('message', __('devices.removed', [], 'ar'));

        $this->assertModelMissing($device);
    }

    public function test_user_cannot_remove_another_users_device(): void
    {
        $device = UserDevice::factory()->create(['push_token' => 'other-token']);
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/v1/devices', ['push_token' => 'other-token'])
            ->assertNotFound()
            ->assertJsonPath('message', __('api.not_found', [], 'ar'));
        $this->deleteJson('/api/v1/devices', ['push_token' => 'unknown'])->assertNotFound();

        $this->assertModelExists($device);
    }

    public function test_destroy_requires_push_token(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/v1/devices', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.push_token.0', __('devices.validation.push_token_required', [], 'ar'));
    }

    public function test_device_routes_require_user_token(): void
    {
        $this->postJson('/api/v1/devices', [])->assertUnauthorized();
        $this->deleteJson('/api/v1/devices', [])->assertUnauthorized();

        Sanctum::actingAs(Staff::factory()->create());

        $this->postJson('/api/v1/devices', ['platform' => 'android', 'push_token' => 'staff'])->assertForbidden();
        $this->deleteJson('/api/v1/devices', ['push_token' => 'staff'])->assertForbidden();
    }
}

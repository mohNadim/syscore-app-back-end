<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'ar');
    }

    public function test_user_can_view_own_profile(): void
    {
        $user = Sanctum::actingAs(User::factory()->create(['full_name' => 'سامر', 'preferred_lang' => 'en']));

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertExactJsonStructure(['data' => ['id', 'phone', 'full_name', 'preferred_lang', 'is_active', 'last_login_at', 'created_at']])
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.phone', $user->phone)
            ->assertJsonPath('data.full_name', 'سامر')
            ->assertJsonPath('data.preferred_lang', 'en');
    }

    public function test_user_can_update_name_and_language(): void
    {
        $user = Sanctum::actingAs(User::factory()->create(['preferred_lang' => 'ar']));

        $this->patchJson('/api/v1/me', ['full_name' => '  محمد نديم  ', 'preferred_lang' => 'en'])
            ->assertOk()
            ->assertJsonPath('message', __('profile.updated', [], 'ar'))
            ->assertJsonPath('data.full_name', 'محمد نديم')
            ->assertJsonPath('data.preferred_lang', 'en');

        $user->refresh();
        $this->assertSame('محمد نديم', $user->full_name);
        $this->assertSame('en', $user->preferred_lang);
    }

    public function test_partial_update_keeps_other_fields(): void
    {
        $user = Sanctum::actingAs(User::factory()->create(['full_name' => 'Old Name', 'preferred_lang' => 'ar']));

        $this->patchJson('/api/v1/me', ['preferred_lang' => 'en'])->assertOk();

        $user->refresh();
        $this->assertSame('Old Name', $user->full_name);
        $this->assertSame('en', $user->preferred_lang);
    }

    public function test_phone_and_status_cannot_be_changed(): void
    {
        $user = Sanctum::actingAs(User::factory()->create());
        $phone = $user->phone;

        $this->patchJson('/api/v1/me', ['phone' => '+963900000000', 'is_active' => false, 'full_name' => 'Name'])->assertOk();

        $user->refresh();
        $this->assertSame($phone, $user->phone);
        $this->assertTrue($user->is_active);
    }

    public function test_update_validates_input_in_both_languages(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/v1/me', ['full_name' => '', 'preferred_lang' => 'fr'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.full_name.0', __('profile.validation.full_name_required', [], 'ar'))
            ->assertJsonPath('errors.preferred_lang.0', __('profile.validation.preferred_lang_invalid', [], 'ar'));

        $this->withHeader('Accept-Language', 'en')
            ->patchJson('/api/v1/me', ['full_name' => 'a'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.full_name.0', 'The full name must be between 2 and 150 characters.');

        $this->patchJson('/api/v1/me', ['full_name' => str_repeat('a', 151)])
            ->assertJsonPath('errors.full_name.0', __('profile.validation.full_name_length', [], 'en'));

        $this->patchJson('/api/v1/me', ['full_name' => ['array']])
            ->assertJsonPath('errors.full_name.0', __('profile.validation.full_name_invalid', [], 'en'));
    }

    public function test_profile_requires_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->patchJson('/api/v1/me', ['full_name' => 'Name'])->assertUnauthorized();
    }

    public function test_profile_rejects_staff_token(): void
    {
        Sanctum::actingAs(Staff::factory()->create());

        $this->getJson('/api/v1/me')->assertForbidden();
        $this->patchJson('/api/v1/me', ['full_name' => 'Name'])->assertForbidden();
    }

    public function test_disabled_user_cannot_access_profile(): void
    {
        Sanctum::actingAs(User::factory()->inactive()->create());

        $this->getJson('/api/v1/me')->assertForbidden();
    }
}

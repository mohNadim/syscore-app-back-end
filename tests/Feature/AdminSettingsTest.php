<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\User;
use App\Services\SettingStore;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/admin/settings';

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

    private function actingAsSettingsManager(): Staff
    {
        $staff = $this->staffWithPermissions(['settings.manage']);
        Sanctum::actingAs($staff);

        return $staff;
    }

    public function test_show_returns_defaults_when_table_is_empty(): void
    {
        $this->actingAsSettingsManager();

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.booking.advance_window_days', 14)
            ->assertJsonPath('data.booking.deposit_percent', 30)
            ->assertJsonPath('data.otp.channel', 'sms')
            ->assertJsonPath('data.subscription.expiry_reminder_days', 7)
            ->assertJsonPath('data.app.display_timezone', 'Asia/Damascus')
            ->assertJsonPath('data.app.contact.phone', null)
            ->assertJsonPath('meta.updated_at', null);
    }

    public function test_show_returns_stored_values_and_ignores_unknown_keys(): void
    {
        $this->actingAsSettingsManager();
        Setting::create(['key' => 'booking.deposit_percent', 'value' => 55]);
        Setting::create(['key' => 'unknown.thing', 'value' => 1]);

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.booking.deposit_percent', 55)
            ->assertJsonPath('data.booking.slot_minutes', 60)
            ->assertJsonMissingPath('data.unknown')
            ->assertJsonPath('meta.updated_at', fn ($value) => $value !== null);
    }

    public function test_partial_update_changes_only_sent_keys_and_audits_each_change(): void
    {
        $staff = $this->actingAsSettingsManager();
        $this->seed(SettingSeeder::class);

        $this->putJson(self::URL, [
            'booking' => ['deposit_percent' => '40', 'slot_minutes' => 60],
            'otp' => ['channel' => 'whatsapp'],
        ])
            ->assertOk()
            ->assertJsonPath('message', __('admin_settings.updated'))
            ->assertJsonPath('data.booking.deposit_percent', 40)
            ->assertJsonPath('data.booking.advance_window_days', 14)
            ->assertJsonPath('data.otp.channel', 'whatsapp');

        $deposit = Setting::firstWhere('key', 'booking.deposit_percent');
        $this->assertSame(40, $deposit->value);
        $this->assertSame($staff->id, $deposit->updated_by_staff_id);
        $this->assertNull(Setting::firstWhere('key', 'booking.advance_window_days')->updated_by_staff_id);

        $this->assertSame(2, AuditLog::where('action', 'setting.updated')->count());
        $log = AuditLog::where('action', 'setting.updated')->where('before->key', 'booking.deposit_percent')->first();
        $this->assertSame('setting', $log->entity_type);
        $this->assertSame($deposit->id, $log->entity_id);
        $this->assertSame(['key' => 'booking.deposit_percent', 'value' => 30], $log->before);
        $this->assertSame(['key' => 'booking.deposit_percent', 'value' => 40], $log->after);
    }

    public function test_update_creates_missing_row_and_skips_unchanged_values(): void
    {
        $this->actingAsSettingsManager();

        $this->putJson(self::URL, ['booking' => ['deposit_percent' => 30]])->assertOk();

        $this->assertNull(Setting::firstWhere('key', 'booking.deposit_percent'));
        $this->assertSame(0, AuditLog::where('action', 'setting.updated')->count());

        $this->putJson(self::URL, ['booking' => ['deposit_percent' => 31]])->assertOk();

        $this->assertSame(31, Setting::firstWhere('key', 'booking.deposit_percent')->value);
        $this->assertSame(1, AuditLog::where('action', 'setting.updated')->count());
    }

    public function test_contact_update_merges_unsent_sub_fields(): void
    {
        $this->actingAsSettingsManager();
        Setting::create(['key' => 'app.contact', 'value' => ['phone' => '+963111', 'whatsapp' => null, 'address_ar' => 'دمشق', 'address_en' => 'Damascus']]);

        $this->putJson(self::URL, ['app' => ['contact' => ['whatsapp' => '+963999', 'phone' => null, 'extra' => 'x']]])
            ->assertOk()
            ->assertJsonPath('data.app.contact', ['phone' => null, 'whatsapp' => '+963999', 'address_ar' => 'دمشق', 'address_en' => 'Damascus']);

        $this->assertSame(['phone', 'whatsapp', 'address_ar', 'address_en'], array_keys(Setting::firstWhere('key', 'app.contact')->value));
    }

    public function test_validation_errors_in_arabic(): void
    {
        $this->actingAsSettingsManager();

        $this->putJson(self::URL, [
            'booking' => ['deposit_percent' => 150, 'slot_minutes' => 45, 'advance_window_days' => 'abc'],
            'app' => ['display_timezone' => 'Mars/Base', 'contact' => ['phone' => str_repeat('1', 21)]],
            'otp' => ['channel' => 'email'],
        ])
            ->assertStatus(422)
            ->assertJson(['errors' => ['booking.deposit_percent' => [__('admin_settings.validation.deposit_percent')]]])
            ->assertJson(['errors' => ['booking.slot_minutes' => [__('admin_settings.validation.slot_minutes')]]])
            ->assertJson(['errors' => ['booking.advance_window_days' => [__('admin_settings.validation.advance_window_days')]]])
            ->assertJson(['errors' => ['app.display_timezone' => [__('admin_settings.validation.display_timezone')]]])
            ->assertJson(['errors' => ['app.contact.phone' => [__('admin_settings.validation.contact_phone')]]])
            ->assertJson(['errors' => ['otp.channel' => [__('admin_settings.validation.otp_channel')]]]);

        $this->assertStringContainsString('العربون', __('admin_settings.validation.deposit_percent'));
    }

    public function test_validation_errors_in_english(): void
    {
        $this->actingAsSettingsManager();

        $this->withHeader('Accept-Language', 'en')
            ->putJson(self::URL, ['booking' => ['deposit_percent' => 150], 'otp' => ['channel' => 'email']])
            ->assertStatus(422)
            ->assertJson(['errors' => ['booking.deposit_percent' => ['The deposit percentage must be a whole number from 0 to 100.']]])
            ->assertJson(['errors' => ['otp.channel' => ['The verification channel must be one of: sms, whatsapp, telegram.']]]);
    }

    public function test_empty_body_is_rejected(): void
    {
        $this->actingAsSettingsManager();

        $this->putJson(self::URL, [])
            ->assertStatus(422)
            ->assertJson(['errors' => ['settings' => [__('admin_settings.validation.empty')]]]);

        $this->putJson(self::URL, ['unknown' => ['x' => 1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('settings');
    }

    public function test_setting_store_reflects_updated_value_and_falls_back_to_defaults(): void
    {
        $this->actingAsSettingsManager();
        $store = app(SettingStore::class);

        $this->assertSame(60, $store->get('otp.resend_cooldown_seconds'));
        $this->assertSame(9, $store->get('unknown.key', 9));
        $this->assertNull($store->get('unknown.key'));

        $this->putJson(self::URL, ['otp' => ['resend_cooldown_seconds' => 120]])->assertOk();

        $this->assertSame(120, $store->get('otp.resend_cooldown_seconds'));
    }

    public function test_requires_authentication_and_permission(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $this->putJson(self::URL, ['otp' => ['max_attempts' => 3]])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson(self::URL)->assertForbidden();

        Sanctum::actingAs($this->staffWithPermissions(['sports.manage']));
        $this->getJson(self::URL)->assertForbidden();
        $this->putJson(self::URL, ['otp' => ['max_attempts' => 3]])->assertForbidden();
    }
}

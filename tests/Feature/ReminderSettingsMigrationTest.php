<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderSettingsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_moves_legacy_reminder_settings_to_the_canonical_keys(): void
    {
        Setting::query()->create(['key' => 'academy.booking_reminder_hours', 'value' => 3]);
        Setting::query()->create(['key' => 'academy.subscription_expiry_notice_days', 'value' => 10]);

        (require database_path('migrations/2026_11_04_000001_unify_reminder_settings.php'))->up();

        $this->assertSame(180, Setting::query()->firstWhere('key', 'booking.reminder_minutes_before')->value);
        $this->assertSame(10, Setting::query()->firstWhere('key', 'subscription.expiry_reminder_days')->value);
        $this->assertSame(0, Setting::query()->whereIn('key', ['academy.booking_reminder_hours', 'academy.subscription_expiry_notice_days'])->count());
    }
}

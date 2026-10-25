<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Default application settings keyed by setting key.
     *
     * @var array<string, int|string|array{phone: string|null, whatsapp: string|null, address_ar: string|null, address_en: string|null}>
     */
    public const DEFAULTS = [
        'booking.advance_window_days' => 14,
        'booking.slot_minutes' => 60,
        'booking.deposit_percent' => 30,
        'booking.deposit_deadline_minutes' => 60,
        'booking.cancellation_deadline_hours' => 24,
        'booking.reminder_minutes_before' => 120,
        'otp.channel' => 'sms',
        'otp.ttl_minutes' => 5,
        'otp.max_attempts' => 5,
        'otp.resend_cooldown_seconds' => 60,
        'subscription.expiry_reminder_days' => 7,
        'app.display_timezone' => 'Asia/Damascus',
        'app.contact' => [
            'phone' => null,
            'whatsapp' => null,
            'address_ar' => null,
            'address_en' => null,
        ],
    ];

    /**
     * Seed default settings without overwriting existing values.
     */
    public function run(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}

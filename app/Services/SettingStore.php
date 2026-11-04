<?php

namespace App\Services;

use App\Models\Setting;

class SettingStore
{
    /**
     * Default application settings keyed by setting key; also the list of every known key.
     *
     * @var array<string, int|string|bool|array{phone: string|null, whatsapp: string|null, address_ar: string|null, address_en: string|null}>
     */
    public const DEFAULTS = [
        'booking.advance_window_days' => 14,
        'booking.slot_minutes' => 60,
        'booking.deposit_percent' => 30,
        'booking.deposit_deadline_minutes' => 60,
        'booking.cancellation_deadline_hours' => 24,
        'booking.reminder_minutes_before' => 120,
        'booking.cash_only_enabled' => true,
        'otp.channel' => 'sms',
        'otp.ttl_minutes' => 5,
        'otp.max_attempts' => 5,
        'otp.resend_cooldown_seconds' => 60,
        'subscription.expiry_reminder_days' => 7,
        'academy.session_generation_days' => 28,
        'academy.enrollment_payment_hours' => 48,
        'academy.waitlist_offer_hours' => 24,
        'app.display_timezone' => 'Asia/Damascus',
        'app.contact' => [
            'phone' => null,
            'whatsapp' => null,
            'address_ar' => null,
            'address_en' => null,
        ],
    ];

    /**
     * Get a setting value by key, falling back to the given default, then to the built-in default of a known key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::query()->firstWhere('key', $key)?->value ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }
}

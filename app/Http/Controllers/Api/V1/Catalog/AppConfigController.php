<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Services\DisplayTimezone;
use App\Services\SettingStore;
use Illuminate\Http\JsonResponse;

class AppConfigController extends Controller
{
    /**
     * Return the public application configuration for the mobile app; OTP channel, TTL and attempts are not exposed.
     */
    public function show(SettingStore $settings, DisplayTimezone $timezone): JsonResponse
    {
        return response()->json(['data' => [
            'display_timezone' => $timezone->name(),
            'contact' => $settings->get('app.contact'),
            'booking' => [
                'advance_window_days' => $settings->get('booking.advance_window_days'),
                'slot_minutes' => $settings->get('booking.slot_minutes'),
                'deposit_percent' => $settings->get('booking.deposit_percent'),
                'deposit_deadline_minutes' => $settings->get('booking.deposit_deadline_minutes'),
                'cancellation_deadline_hours' => $settings->get('booking.cancellation_deadline_hours'),
            ],
            'otp' => [
                'resend_cooldown_seconds' => $settings->get('otp.resend_cooldown_seconds'),
            ],
            'subscription' => [
                'expiry_reminder_days' => $settings->get('subscription.expiry_reminder_days'),
            ],
            'server_time' => $timezone->utcToLocalIso(now()),
        ]]);
    }
}

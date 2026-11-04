<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Move stored reminder settings onto the single canonical keys and drop the duplicates.
     */
    public function up(): void
    {
        $hours = Setting::query()->firstWhere('key', 'academy.booking_reminder_hours');

        if ($hours !== null) {
            Setting::query()->updateOrCreate(
                ['key' => 'booking.reminder_minutes_before'],
                ['value' => (int) $hours->value * 60, 'updated_by_staff_id' => $hours->updated_by_staff_id],
            );
        }

        $days = Setting::query()->firstWhere('key', 'academy.subscription_expiry_notice_days');

        if ($days !== null) {
            Setting::query()->updateOrCreate(
                ['key' => 'subscription.expiry_reminder_days'],
                ['value' => (int) $days->value, 'updated_by_staff_id' => $days->updated_by_staff_id],
            );
        }

        Setting::query()->whereIn('key', ['academy.booking_reminder_hours', 'academy.subscription_expiry_notice_days'])->delete();
    }

    /**
     * Reverse the migration; the removed keys fall back to their defaults.
     */
    public function down(): void {}
};

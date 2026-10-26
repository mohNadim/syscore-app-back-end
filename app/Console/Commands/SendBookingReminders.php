<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Notifications\NotificationEvents;
use App\Services\SettingStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:send-reminders')]
#[Description('Remind users of confirmed bookings starting within the configured reminder window (academy.booking_reminder_hours)')]
class SendBookingReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(NotificationEvents $events, SettingStore $settings): int
    {
        $windowEnd = now()->addHours((int) $settings->get('academy.booking_reminder_hours'));
        $sent = 0;

        Booking::query()
            ->where('status', 'confirmed')
            ->whereNotNull('user_id')
            ->whereNull('reminder_sent_at')
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', $windowEnd)
            ->each(function (Booking $booking) use ($events, &$sent): void {
                if ($events->bookingReminder($booking)) {
                    $booking->forceFill(['reminder_sent_at' => now()])->save();
                    $sent++;
                }
            });

        $this->info("Sent {$sent} booking reminders.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Notifications\NotificationEvents;
use App\Services\SettingStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:send-expiry-notices')]
#[Description('Notify guardians before a subscription ends (academy.subscription_expiry_notice_days) and when it has ended')]
class SendSubscriptionExpiryNotices extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(NotificationEvents $events, SettingStore $settings): int
    {
        $noticeEnd = now()->addDays((int) $settings->get('academy.subscription_expiry_notice_days'));
        $expiring = 0;
        $expired = 0;

        Subscription::query()
            ->where('status', 'active')
            ->whereNull('reminder_7d_sent_at')
            ->where('period_end', '>', now())
            ->where('period_end', '<=', $noticeEnd)
            ->each(function (Subscription $subscription) use ($events, &$expiring): void {
                if ($events->subscriptionExpiring($subscription)) {
                    $subscription->forceFill(['reminder_7d_sent_at' => now()])->save();
                    $expiring++;
                }
            });

        Subscription::query()
            ->whereIn('status', ['active', 'expired'])
            ->whereNull('expiry_notice_sent_at')
            ->where('period_end', '<=', now())
            ->where('period_end', '>', now()->subDays(2))
            ->each(function (Subscription $subscription) use ($events, &$expired): void {
                if ($events->subscriptionExpired($subscription)) {
                    $subscription->forceFill(['expiry_notice_sent_at' => now()])->save();
                    $expired++;
                }
            });

        $this->info("Sent {$expiring} expiring and {$expired} expired subscription notices.");

        return self::SUCCESS;
    }
}

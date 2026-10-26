<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Academies\SubscriptionService;
use App\Services\SettingStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:expire-unpaid')]
#[Description('Cancel subscriptions left unpaid beyond the payment window, freeing the seat')]
class ExpireUnpaidSubscriptions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $subscriptions, SettingStore $settings): int
    {
        $cutoff = now()->subHours((int) $settings->get('academy.enrollment_payment_hours'));
        $cancelled = 0;

        Subscription::query()
            ->where('status', 'pending_payment')
            ->where('created_at', '<=', $cutoff)
            ->each(function (Subscription $subscription) use ($subscriptions, $cutoff, &$cancelled): void {
                $cancelled += $subscriptions->expireUnpaid($subscription, $cutoff) ? 1 : 0;
            });

        $this->info("Cancelled {$cancelled} unpaid subscriptions.");

        return self::SUCCESS;
    }
}

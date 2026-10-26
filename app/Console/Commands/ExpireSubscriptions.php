<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Academies\SubscriptionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:expire')]
#[Description('Mark active subscriptions whose period has ended as expired')]
class ExpireSubscriptions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SubscriptionService $subscriptions): int
    {
        $expired = 0;

        Subscription::query()
            ->where('status', 'active')
            ->where('period_end', '<=', now())
            ->each(function (Subscription $subscription) use ($subscriptions, &$expired): void {
                $expired += $subscriptions->expirePeriod($subscription) ? 1 : 0;
            });

        $this->info("Expired {$expired} subscriptions.");

        return self::SUCCESS;
    }
}

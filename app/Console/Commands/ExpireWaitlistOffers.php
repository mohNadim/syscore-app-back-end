<?php

namespace App\Console\Commands;

use App\Models\WaitlistEntry;
use App\Services\Academies\EnrollmentService;
use App\Services\SettingStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('waitlist:expire-offers')]
#[Description('Withdraw waitlist offers that were not answered in time')]
class ExpireWaitlistOffers extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EnrollmentService $enrollments, SettingStore $settings): int
    {
        $cutoff = now()->subHours((int) $settings->get('academy.waitlist_offer_hours'));
        $withdrawn = 0;

        WaitlistEntry::query()
            ->where('status', 'offered')
            ->where('offered_at', '<=', $cutoff)
            ->each(function (WaitlistEntry $entry) use ($enrollments, $cutoff, &$withdrawn): void {
                $withdrawn += $enrollments->expireOffer($entry, $cutoff) ? 1 : 0;
            });

        $this->info("Withdrew {$withdrawn} waitlist offers.");

        return self::SUCCESS;
    }
}

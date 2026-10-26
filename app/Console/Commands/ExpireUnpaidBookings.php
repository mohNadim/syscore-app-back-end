<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Bookings\BookingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:expire-unpaid')]
#[Description('Expire app bookings whose deposit was not paid before the deadline')]
class ExpireUnpaidBookings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BookingService $bookings): int
    {
        $expired = 0;

        Booking::query()
            ->where('status', 'pending_deposit')
            ->where('deposit_due_at', '<=', now())
            ->each(function (Booking $booking) use ($bookings, &$expired): void {
                $expired += $bookings->expireUnpaid($booking) ? 1 : 0;
            });

        $this->info("Expired {$expired} unpaid bookings.");

        return self::SUCCESS;
    }
}

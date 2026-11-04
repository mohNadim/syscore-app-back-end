<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingSeries;
use App\Services\AuditLogger;
use App\Services\Bookings\BookingService;
use App\Services\DisplayTimezone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('bookings:complete-past')]
#[Description('Complete confirmed bookings that have ended and end booking series whose occurrences are all finished')]
class CompletePastBookings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BookingService $bookings, DisplayTimezone $timezone, AuditLogger $audit): int
    {
        $completed = 0;
        $ended = 0;

        Booking::query()
            ->where('status', 'confirmed')
            ->where('ends_at', '<=', now())
            ->each(function (Booking $booking) use ($bookings, &$completed): void {
                $completed += $bookings->completePast($booking) ? 1 : 0;
            });

        $today = now($timezone->name())->toDateString();

        BookingSeries::query()
            ->where('status', 'active')
            ->where('last_date', '<', $today)
            ->whereDoesntHave('bookings', fn (Builder $query) => $query->whereIn('status', ['pending_deposit', 'confirmed']))
            ->each(function (BookingSeries $series) use ($audit, &$ended): void {
                $series->update(['status' => 'ended']);
                $audit->log('booking_series.ended', $series, ['status' => 'active'], ['status' => 'ended']);
                $ended++;
            });

        $this->info("Completed {$completed} bookings and ended {$ended} booking series.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PriceUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Bookings\QuoteBookingRequest;
use App\Models\User;
use App\Services\DisplayTimezone;
use App\Services\Pricing\PricingService;
use App\Services\SettingStore;
use Illuminate\Http\JsonResponse;

class BookingQuoteController extends Controller
{
    /**
     * Quote a booking: base price, best discount and deposit. Public; when a customer token is sent their category discounts apply.
     * The quote does not check availability.
     *
     * @throws PriceUnavailableException when no price rule covers the whole interval
     */
    public function show(QuoteBookingRequest $request, PricingService $pricing, DisplayTimezone $timezone, SettingStore $settings): JsonResponse
    {
        $user = $request->user('sanctum');
        $user = $user instanceof User ? $user : null;

        $unit = $request->unit();
        $sportId = $request->filled('sport_id') ? $request->integer('sport_id') : null;
        $duration = $request->integer('duration_minutes');
        $startsAt = $request->startsAtUtc();
        $endsAt = $startsAt->addMinutes($duration);

        $quote = $pricing->quoteBooking($unit, $startsAt, $endsAt, $user, $sportId);
        $depositPercent = (int) $settings->get('booking.deposit_percent');

        return response()->json(['data' => [
            'unit_id' => $unit->id,
            'sport_id' => $sportId,
            'starts_at' => $timezone->utcToLocalIso($startsAt),
            'ends_at' => $timezone->utcToLocalIso($endsAt),
            'duration_minutes' => $duration,
            ...$quote->toArray(),
            'deposit_amount' => (int) ceil($quote->finalPrice * $depositPercent / 100),
            'currency' => 'SYP',
        ]]);
    }
}

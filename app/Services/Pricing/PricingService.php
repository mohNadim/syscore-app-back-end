<?php

namespace App\Services\Pricing;

use App\Exceptions\PriceUnavailableException;
use App\Models\AcademyGroup;
use App\Models\BookableUnit;
use App\Models\PriceRule;
use App\Models\User;
use App\Services\DisplayTimezone;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class PricingService
{
    public function __construct(
        protected DiscountResolver $discounts,
        protected DisplayTimezone $timezone,
    ) {}

    /**
     * Price a booking interval from the unit's price rules before any discount.
     *
     * The interval must lie within one local day. Each rule contributes its overlap in minutes times its hourly price,
     * and the sum is rounded once at the end.
     *
     * @throws PriceUnavailableException when part of the interval is not covered by a price rule
     */
    public function bookingBasePrice(BookableUnit $unit, CarbonInterface $startsAt, CarbonInterface $endsAt): int
    {
        $localStart = CarbonImmutable::instance($startsAt)->setTimezone($this->timezone->name());

        $rules = PriceRule::query()
            ->where('unit_id', $unit->id)
            ->where('valid_from', '<=', $localStart->toDateString())
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>=', $localStart->toDateString()))
            ->where(fn ($query) => $query->whereNull('weekday')->orWhere('weekday', $localStart->dayOfWeek))
            ->get();

        return $this->priceFromRules($rules, $startsAt, $endsAt) ?? throw new PriceUnavailableException;
    }

    /**
     * Price an interval from already loaded price rules of one unit, or null when the rules do not cover all of it.
     *
     * Rules are filtered by validity date and weekday here too, so a unit's whole rule set can be passed in.
     *
     * @param  Collection<int, PriceRule>  $rules
     */
    public function priceFromRules(Collection $rules, CarbonInterface $startsAt, CarbonInterface $endsAt): ?int
    {
        $localStart = CarbonImmutable::instance($startsAt)->setTimezone($this->timezone->name());
        $startMinute = $localStart->hour * 60 + $localStart->minute;
        $totalMinutes = (int) round($startsAt->diffInMinutes($endsAt, true));
        $endMinute = $startMinute + $totalMinutes;
        $date = $localStart->toDateString();

        $coveredMinutes = 0;
        $price = 0.0;

        foreach ($rules as $rule) {
            if ($rule->valid_from->toDateString() > $date
                || ($rule->valid_to !== null && $rule->valid_to->toDateString() < $date)
                || ($rule->weekday !== null && $rule->weekday !== $localStart->dayOfWeek)) {
                continue;
            }

            $overlap = min($endMinute, $this->minutesOfDay($rule->end_time)) - max($startMinute, $this->minutesOfDay($rule->start_time));

            if ($overlap > 0) {
                $coveredMinutes += $overlap;
                $price += $overlap * $rule->price_per_hour / 60;
            }
        }

        return $coveredMinutes < $totalMinutes ? null : (int) round($price);
    }

    /**
     * Price a booking including the best discount for the customer, evaluated now.
     *
     * @throws PriceUnavailableException when the interval has no price
     */
    public function quoteBooking(BookableUnit $unit, CarbonInterface $startsAt, CarbonInterface $endsAt, ?User $user, ?int $sportId): PriceQuote
    {
        return $this->discounts->resolve('booking', $this->bookingBasePrice($unit, $startsAt, $endsAt), $user, $sportId, null);
    }

    /**
     * Price a monthly subscription of an academy group including the best discount for the customer, evaluated now.
     */
    public function quoteSubscription(AcademyGroup $group, ?User $user): PriceQuote
    {
        $group->loadMissing('academy');

        return $this->discounts->resolve('subscription', $group->monthly_price, $user, $group->academy->sport_id, $group->academy_id);
    }

    /**
     * Convert a TIME value (HH:MM or HH:MM:SS) to minutes since midnight.
     */
    private function minutesOfDay(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }
}

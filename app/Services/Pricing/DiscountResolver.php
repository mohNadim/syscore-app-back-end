<?php

namespace App\Services\Pricing;

use App\Models\Discount;
use App\Models\User;
use App\Services\DisplayTimezone;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DiscountResolver
{
    public function __construct(
        protected DiscountSelectionStrategy $strategy,
        protected DisplayTimezone $timezone,
    ) {}

    /**
     * Get the discounts a customer may use for a booking or subscription created at the given moment.
     *
     * Every time condition (validity period, daily window, weekday) is evaluated at `$at`, the creation time of the
     * booking or subscription, never at the time the booked slot takes place. All conditions must hold together.
     *
     * @param  string  $target  booking or subscription
     * @return Collection<int, Discount>
     */
    public function eligible(string $target, ?User $user, ?int $sportId, ?int $academyId, ?CarbonInterface $at = null): Collection
    {
        $at = CarbonImmutable::instance($at ?? now());
        $local = $at->setTimezone($this->timezone->name());
        $localTime = $local->format('H:i');

        $memberCategoryIds = $user === null
            ? []
            : $user->categories()->where('user_categories.is_active', true)->pluck('user_categories.id')->all();

        return Discount::query()
            ->with(['categories', 'sports', 'academies'])
            ->where('is_active', true)
            ->whereIn('target', [$target, 'all'])
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>=', $at)
            ->orderBy('id')
            ->get()
            ->filter(function (Discount $discount) use ($local, $localTime, $memberCategoryIds, $sportId, $academyId): bool {
                if ($discount->time_from !== null && $discount->time_to !== null
                    && ($localTime < substr($discount->time_from, 0, 5) || $localTime >= substr($discount->time_to, 0, 5))) {
                    return false;
                }

                if (! $discount->appliesOnWeekday($local->dayOfWeek)) {
                    return false;
                }

                if ($discount->categories->isNotEmpty() && $discount->categories->pluck('id')->intersect($memberCategoryIds)->isEmpty()) {
                    return false;
                }

                if ($discount->sports->isNotEmpty() && ($sportId === null || ! $discount->sports->contains('id', $sportId))) {
                    return false;
                }

                return ! ($discount->academies->isNotEmpty() && ($academyId === null || ! $discount->academies->contains('id', $academyId)));
            })
            ->values();
    }

    /**
     * Price a base amount with the best eligible discount as decided by the selection strategy.
     */
    public function resolve(string $target, int $basePrice, ?User $user, ?int $sportId, ?int $academyId): PriceQuote
    {
        $candidates = $this->eligible($target, $user, $sportId, $academyId)
            ->map(fn (Discount $discount) => ['discount' => $discount, 'amount' => $this->amountFor($discount, $basePrice)])
            ->filter(fn (array $candidate) => $candidate['amount'] > 0)
            ->values();

        $chosen = $this->strategy->choose($candidates, $basePrice);

        if ($chosen === null) {
            return new PriceQuote($basePrice, null, 0, $basePrice);
        }

        $amount = min($chosen['amount'], $basePrice);

        return new PriceQuote($basePrice, $chosen['discount'], $amount, $basePrice - $amount);
    }

    /**
     * The amount a discount takes off a base price: a rounded share for percent, the value capped at the price for fixed.
     */
    public function amountFor(Discount $discount, int $basePrice): int
    {
        return $discount->type === 'percent'
            ? (int) round($basePrice * $discount->value / 100)
            : min($discount->value, $basePrice);
    }
}

<?php

namespace App\Services\Pricing;

use App\Models\Discount;
use Illuminate\Support\Collection;

/**
 * Decides which of the eligible discounts is applied; bind another implementation in the container to change the rule.
 */
interface DiscountSelectionStrategy
{
    /**
     * Pick the discount to apply.
     *
     * @param  Collection<int, array{discount: Discount, amount: int}>  $candidates  every eligible discount with the amount it would take off the base price
     * @return array{discount: Discount, amount: int}|null the chosen candidate, or null to apply no discount
     */
    public function choose(Collection $candidates, int $basePrice): ?array;
}

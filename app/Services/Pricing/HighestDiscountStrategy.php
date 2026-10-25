<?php

namespace App\Services\Pricing;

use Illuminate\Support\Collection;

/**
 * Applies the discount that takes the most off the price; on a tie the lowest discount id wins.
 */
class HighestDiscountStrategy implements DiscountSelectionStrategy
{
    public function choose(Collection $candidates, int $basePrice): ?array
    {
        return $candidates
            ->sort(fn (array $a, array $b) => [$b['amount'], $a['discount']->id] <=> [$a['amount'], $b['discount']->id])
            ->first();
    }
}

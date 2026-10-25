<?php

namespace App\Services\Pricing;

use App\Models\PriceRule;

class PriceRuleOverlapChecker
{
    /**
     * Find an existing rule of the unit that overlaps the given rule values in weekday, time of day and validity period.
     * A null weekday matches every day, a null validity end is open-ended and touching time edges do not overlap.
     */
    public function findOverlap(
        int $unitId,
        ?int $weekday,
        string $startTime,
        string $endTime,
        string $validFrom,
        ?string $validTo,
        ?int $ignoreId = null,
    ): ?PriceRule {
        return PriceRule::query()
            ->where('unit_id', $unitId)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->when($weekday !== null, fn ($query) => $query->where(
                fn ($inner) => $inner->whereNull('weekday')->orWhere('weekday', $weekday),
            ))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->when($validTo !== null, fn ($query) => $query->where('valid_from', '<=', $validTo))
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhere('valid_to', '>=', $validFrom))
            ->orderBy('id')
            ->first();
    }
}

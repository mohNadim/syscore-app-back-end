<?php

namespace App\Models;

use Database\Factories\PriceRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['unit_id', 'weekday', 'start_time', 'end_time', 'is_peak', 'price_per_hour', 'valid_from', 'valid_to'])]
class PriceRule extends Model
{
    /** @use HasFactory<PriceRuleFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'is_peak' => 'boolean',
            'price_per_hour' => 'integer',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BookableUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(BookableUnit::class);
    }
}

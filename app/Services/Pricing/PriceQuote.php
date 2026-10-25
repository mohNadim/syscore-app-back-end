<?php

namespace App\Services\Pricing;

use App\Models\Discount;

/**
 * The price of a booking or subscription before and after the best applicable discount, in whole SYP.
 */
final readonly class PriceQuote
{
    public function __construct(
        public int $basePrice,
        public ?Discount $discount,
        public int $discountAmount,
        public int $finalPrice,
    ) {}

    /**
     * @return array{base_price: int, discount: array{id: int, name_ar: string, name_en: string|null, type: string, value: int}|null, discount_amount: int, final_price: int}
     */
    public function toArray(): array
    {
        return [
            'base_price' => $this->basePrice,
            'discount' => $this->discount === null ? null : [
                'id' => $this->discount->id,
                'name_ar' => $this->discount->name_ar,
                'name_en' => $this->discount->name_en,
                'type' => $this->discount->type,
                'value' => $this->discount->value,
            ],
            'discount_amount' => $this->discountAmount,
            'final_price' => $this->finalPrice,
        ];
    }
}

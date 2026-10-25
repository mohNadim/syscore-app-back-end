<?php

namespace App\Services\Payments\ShamCash;

final readonly class ShamCashCheckout
{
    public function __construct(
        public string $reference,
        public string $checkoutUrl,
    ) {}
}

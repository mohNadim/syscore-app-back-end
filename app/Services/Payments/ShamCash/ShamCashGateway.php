<?php

namespace App\Services\Payments\ShamCash;

use App\Models\Payment;

interface ShamCashGateway
{
    /**
     * Open a checkout session at the provider for a pending payment.
     */
    public function initiate(Payment $payment): ShamCashCheckout;

    /**
     * Refund an amount of a succeeded payment and return the provider's refund reference.
     */
    public function refund(Payment $payment, int $amount): string;

    /**
     * Check the webhook signature over the raw request body.
     */
    public function verifySignature(string $payload, ?string $signature): bool;
}

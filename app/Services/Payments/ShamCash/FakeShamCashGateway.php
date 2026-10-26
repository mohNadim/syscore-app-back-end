<?php

namespace App\Services\Payments\ShamCash;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fake gateway that issues local references until the real Sham Cash API is wired in.
 */
class FakeShamCashGateway implements ShamCashGateway
{
    public function initiate(Payment $payment): ShamCashCheckout
    {
        $reference = 'SC-'.strtoupper(Str::random(16));

        return new ShamCashCheckout($reference, config('services.sham_cash.checkout_url').'?ref='.$reference);
    }

    public function refund(Payment $payment, int $amount): string
    {
        $reference = 'SCR-'.strtoupper(Str::random(16));

        Log::info('Sham Cash refund issued', ['payment_id' => $payment->id, 'amount' => $amount, 'reference' => $reference]);

        return $reference;
    }

    public function verifySignature(string $payload, ?string $signature): bool
    {
        return hash_equals(hash_hmac('sha256', $payload, (string) config('services.sham_cash.webhook_secret')), (string) $signature);
    }
}

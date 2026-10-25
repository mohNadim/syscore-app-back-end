<?php

namespace App\Http\Resources;

use App\Models\Payment;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = app(DisplayTimezone::class);

        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'amount' => $this->amount,
            'method' => $this->method,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'booking_id' => $this->booking_id,
            'subscription_id' => $this->subscription_id,
            'provider_reference' => $this->provider_reference,
            'refunded_amount' => (int) $this->refunds()->sum('amount'),
            'paid_at' => $this->paid_at ? $timezone->utcToLocalIso($this->paid_at) : null,
            'created_at' => $this->created_at ? $timezone->utcToLocalIso($this->created_at) : null,
        ];
    }
}

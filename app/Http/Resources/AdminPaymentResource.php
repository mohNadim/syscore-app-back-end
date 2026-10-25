<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * @mixin Payment
 */
class AdminPaymentResource extends PaymentResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'payer_name' => $this->payer_name,
            'payer_phone' => $this->payer_phone,
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'name' => $this->user->full_name,
                'phone' => $this->user->phone,
            ]),
            'recorded_by' => $this->whenLoaded('recordedByStaff', fn () => $this->recordedByStaff === null ? null : [
                'id' => $this->recordedByStaff->id,
                'name' => $this->recordedByStaff->full_name,
            ]),
            'refunds' => RefundResource::collection($this->whenLoaded('refunds')),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
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
            'enrollment_id' => $this->enrollment_id,
            'enrollment' => new EnrollmentResource($this->whenLoaded('enrollment')),
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'order_number' => $this->order_number,
            'period_start' => $this->period_start ? $timezone->utcToLocalIso($this->period_start) : null,
            'period_end' => $this->period_end ? $timezone->utcToLocalIso($this->period_end) : null,
            'base_price' => $this->base_price,
            'discount' => $this->whenLoaded('discount', fn () => $this->discount === null ? null : [
                'id' => $this->discount->id,
                'name_ar' => $this->discount->name_ar,
                'name_en' => $this->discount->name_en,
                'type' => $this->discount->type,
                'value' => $this->discount->value,
            ]),
            'discount_amount' => $this->discount_amount,
            'final_price' => $this->final_price,
            'renewed_from_id' => $this->renewed_from_id,
            'created_at' => $this->created_at ? $timezone->utcToLocalIso($this->created_at) : null,
        ];
    }
}

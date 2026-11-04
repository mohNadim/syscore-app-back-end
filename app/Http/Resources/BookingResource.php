<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class BookingResource extends JsonResource
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
            'booking_number' => $this->booking_number,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->user === null ? null : [
                'id' => $this->user->id,
                'full_name' => $this->user->full_name,
                'phone' => $this->user->phone,
            ]),
            'guest_name' => $this->guest_name,
            'guest_phone' => $this->guest_phone,
            'unit_id' => $this->unit_id,
            'unit' => new BookableUnitResource($this->whenLoaded('unit')),
            'sport_id' => $this->sport_id,
            'sport' => new SportResource($this->whenLoaded('sport')),
            'series_id' => $this->series_id,
            'series' => new BookingSeriesResource($this->whenLoaded('series')),
            'starts_at' => $timezone->utcToLocalIso($this->starts_at),
            'ends_at' => $timezone->utcToLocalIso($this->ends_at),
            'duration_minutes' => (int) $this->starts_at->diffInMinutes($this->ends_at, true),
            'status' => $this->status,
            'source' => $this->source,
            'payment_mode' => $this->payment_mode,
            'base_price' => $this->base_price,
            'discount_id' => $this->discount_id,
            'discount' => $this->whenLoaded('discount', fn () => $this->discount === null ? null : [
                'id' => $this->discount->id,
                'name_ar' => $this->discount->name_ar,
                'name_en' => $this->discount->name_en,
                'type' => $this->discount->type,
                'value' => $this->discount->value,
            ]),
            'discount_amount' => $this->discount_amount,
            'final_price' => $this->final_price,
            'deposit_amount' => $this->deposit_amount,
            'deposit_due_at' => $this->deposit_due_at ? $timezone->utcToLocalIso($this->deposit_due_at) : null,
            'paid_amount' => $this->paid_amount,
            'balance_due' => in_array($this->status, ['cancelled', 'expired'], true) ? 0 : max(0, $this->final_price - $this->paid_amount),
            'refundable_amount' => $this->status === 'cancelled' ? max(0, $this->paid_amount - $this->refund_amount) : 0,
            'cancelled_at' => $this->cancelled_at ? $timezone->utcToLocalIso($this->cancelled_at) : null,
            'cancel_reason' => $this->cancel_reason,
            'refund_amount' => $this->refund_amount,
            'created_by_staff_id' => $this->created_by_staff_id,
            'created_by_staff' => $this->whenLoaded('createdByStaff', fn () => $this->createdByStaff === null ? null : [
                'id' => $this->createdByStaff->id,
                'full_name' => $this->createdByStaff->full_name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

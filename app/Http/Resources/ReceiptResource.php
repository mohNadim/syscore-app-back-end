<?php

namespace App\Http\Resources;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\DisplayTimezone;
use App\Services\SettingStore;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class ReceiptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = app(DisplayTimezone::class);
        $refunded = (int) $this->refunds->sum('amount');

        return [
            'receipt_number' => $this->receipt_number,
            'issued_at' => $this->paid_at ? $timezone->utcToLocalIso($this->paid_at) : null,
            'status' => $this->status,
            'method' => $this->method,
            'purpose' => $this->purpose,
            'amount' => $this->amount,
            'refunded_amount' => $refunded,
            'net_amount' => $this->amount - $refunded,
            'payer' => [
                'name' => $this->payer_name ?? $this->user?->full_name,
                'phone' => $this->payer_phone ?? $this->user?->phone,
            ],
            'recorded_by' => $this->recordedByStaff?->full_name,
            'item' => $this->itemDetails($timezone),
            'facility_contact' => app(SettingStore::class)->get('app.contact'),
        ];
    }

    /**
     * Describe the booking or subscription the payment paid for.
     *
     * @return array<string, mixed>|null
     */
    private function itemDetails(DisplayTimezone $timezone): ?array
    {
        if ($this->booking instanceof Booking) {
            return [
                'type' => 'booking',
                'booking_number' => $this->booking->booking_number,
                'unit_name_ar' => $this->booking->unit?->name_ar,
                'unit_name_en' => $this->booking->unit?->name_en,
                'starts_at' => $this->booking->starts_at ? $timezone->utcToLocalIso($this->booking->starts_at) : null,
                'ends_at' => $this->booking->ends_at ? $timezone->utcToLocalIso($this->booking->ends_at) : null,
            ];
        }

        if ($this->subscription instanceof Subscription) {
            $group = $this->subscription->enrollment?->group;

            return [
                'type' => 'subscription',
                'academy_name_ar' => $group?->academy?->name_ar,
                'academy_name_en' => $group?->academy?->name_en,
                'group_name_ar' => $group?->name_ar,
                'group_name_en' => $group?->name_en,
                'child_name' => $this->subscription->enrollment?->child?->full_name,
                'period_start' => $this->subscription->period_start ? $timezone->utcToLocalIso($this->subscription->period_start) : null,
                'period_end' => $this->subscription->period_end ? $timezone->utcToLocalIso($this->subscription->period_end) : null,
            ];
        }

        return null;
    }
}

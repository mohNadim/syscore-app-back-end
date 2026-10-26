<?php

namespace App\Http\Resources;

use App\Models\Refund;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Refund
 */
class RefundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'method' => $this->method,
            'reason' => $this->reason,
            'provider_reference' => $this->provider_reference,
            'processed_by_staff_id' => $this->processed_by_staff_id,
            'created_at' => $this->created_at ? app(DisplayTimezone::class)->utcToLocalIso($this->created_at) : null,
        ];
    }
}

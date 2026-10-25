<?php

namespace App\Http\Resources;

use App\Models\Closure;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Closure
 */
class ClosureResource extends JsonResource
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
            'facility_id' => $this->facility_id,
            'space_id' => $this->space_id,
            'facility' => new FacilityResource($this->whenLoaded('facility')),
            'space' => new SpaceResource($this->whenLoaded('space')),
            'starts_at' => $timezone->utcToLocalIso($this->starts_at),
            'ends_at' => $timezone->utcToLocalIso($this->ends_at),
            'reason_ar' => $this->reason_ar,
            'reason_en' => $this->reason_en,
            'created_by_staff_id' => $this->created_by_staff_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

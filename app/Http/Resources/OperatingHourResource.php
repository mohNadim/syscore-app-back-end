<?php

namespace App\Http\Resources;

use App\Models\OperatingHour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OperatingHour
 */
class OperatingHourResource extends JsonResource
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
            'facility_id' => $this->facility_id,
            'weekday' => $this->weekday,
            'open_time' => substr((string) $this->open_time, 0, 5),
            'close_time' => substr((string) $this->close_time, 0, 5),
        ];
    }
}

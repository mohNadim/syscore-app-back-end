<?php

namespace App\Http\Resources;

use App\Models\GroupSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GroupSchedule
 */
class GroupScheduleResource extends JsonResource
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
            'group_id' => $this->group_id,
            'unit_id' => $this->unit_id,
            'unit' => new BookableUnitResource($this->whenLoaded('unit')),
            'weekday' => $this->weekday,
            'start_time' => substr((string) $this->start_time, 0, 5),
            'end_time' => substr((string) $this->end_time, 0, 5),
            'valid_from' => $this->valid_from->toDateString(),
            'valid_to' => $this->valid_to?->toDateString(),
        ];
    }
}

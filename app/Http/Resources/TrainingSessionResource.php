<?php

namespace App\Http\Resources;

use App\Models\TrainingSession;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrainingSession
 */
class TrainingSessionResource extends JsonResource
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
            'group_id' => $this->group_id,
            'group' => new AcademyGroupResource($this->whenLoaded('group')),
            'schedule_id' => $this->schedule_id,
            'unit_id' => $this->unit_id,
            'unit' => new BookableUnitResource($this->whenLoaded('unit')),
            'coach_id' => $this->coach_id,
            'coach' => new CoachResource($this->whenLoaded('coach')),
            'starts_at' => $timezone->utcToLocalIso($this->starts_at),
            'ends_at' => $timezone->utcToLocalIso($this->ends_at),
            'original_starts_at' => $this->original_starts_at ? $timezone->utcToLocalIso($this->original_starts_at) : null,
            'status' => $this->status,
            'change_reason' => $this->change_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

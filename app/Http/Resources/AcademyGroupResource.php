<?php

namespace App\Http\Resources;

use App\Models\AcademyGroup;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AcademyGroup
 */
class AcademyGroupResource extends JsonResource
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
            'academy_id' => $this->academy_id,
            'academy' => new AcademyResource($this->whenLoaded('academy')),
            'coach_id' => $this->coach_id,
            'coach' => new CoachResource($this->whenLoaded('coach')),
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'age_min' => $this->age_min,
            'age_max' => $this->age_max,
            'gender' => $this->gender,
            'capacity' => $this->capacity,
            'level' => $this->level,
            'level_ar' => $this->level_ar ?? $this->level,
            'level_en' => $this->level_en,
            'monthly_price' => $this->monthly_price,
            'subscription_days' => $this->subscription_days,
            'is_active' => $this->is_active,
            'schedules' => GroupScheduleResource::collection($this->whenLoaded('schedules')),
            'created_at' => $this->created_at ? app(DisplayTimezone::class)->utcToLocalIso($this->created_at) : null,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * A training session with its attendance counters (`enrolled_count`, `present_count`, `absent_count`, `excused_count`; excused is not an absence).
 */
class AttendanceSessionResource extends TrainingSessionResource
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
            'enrolled_count' => (int) $this->getAttribute('enrolled_count'),
            'present_count' => (int) $this->getAttribute('present_count'),
            'absent_count' => (int) $this->getAttribute('absent_count'),
            'excused_count' => (int) $this->getAttribute('excused_count'),
        ];
    }
}

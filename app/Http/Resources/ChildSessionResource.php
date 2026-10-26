<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * A training session with the attendance status of one child (null when not marked yet).
 */
class ChildSessionResource extends TrainingSessionResource
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
            'attendance_status' => $this->attendance->first()?->status,
        ];
    }
}

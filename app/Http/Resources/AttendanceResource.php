<?php

namespace App\Http\Resources;

use App\Models\Attendance;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attendance
 */
class AttendanceResource extends JsonResource
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
            'session_id' => $this->session_id,
            'session' => new TrainingSessionResource($this->whenLoaded('session')),
            'child_id' => $this->child_id,
            'status' => $this->status,
            'marked_by_staff_id' => $this->marked_by_staff_id,
            'marked_at' => app(DisplayTimezone::class)->utcToLocalIso($this->marked_at),
        ];
    }
}

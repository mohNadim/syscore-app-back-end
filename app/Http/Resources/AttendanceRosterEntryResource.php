<?php

namespace App\Http\Resources;

use App\Models\Child;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One enrolled child with the attendance status recorded for a session (null when not marked yet).
 *
 * @mixin Child
 */
class AttendanceRosterEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $record = $this->attendance->first();

        return [
            'child_id' => $this->id,
            'full_name' => $this->full_name,
            'status' => $record?->status,
            'marked_at' => $record ? app(DisplayTimezone::class)->utcToLocalIso($record->marked_at) : null,
        ];
    }
}

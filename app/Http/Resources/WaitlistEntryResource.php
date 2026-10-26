<?php

namespace App\Http\Resources;

use App\Models\WaitlistEntry;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `position` attribute is set by the controller; it is null unless the entry is waiting.
 *
 * @mixin WaitlistEntry
 */
class WaitlistEntryResource extends JsonResource
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
            'child_id' => $this->child_id,
            'child' => new ChildResource($this->whenLoaded('child')),
            'status' => $this->status,
            'position' => $this->getAttribute('position'),
            'offered_at' => $this->offered_at ? $timezone->utcToLocalIso($this->offered_at) : null,
            'created_at' => $this->created_at ? $timezone->utcToLocalIso($this->created_at) : null,
        ];
    }
}

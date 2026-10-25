<?php

namespace App\Http\Resources;

use App\Models\Academy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Academy
 */
class AcademyResource extends JsonResource
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
            'sport_id' => $this->sport_id,
            'sport' => new SportResource($this->whenLoaded('sport')),
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'cover_url' => $this->cover_url,
            'is_active' => $this->is_active,
            'groups' => AcademyGroupResource::collection($this->whenLoaded('groups')),
            'groups_count' => $this->whenCounted('groups'),
        ];
    }
}

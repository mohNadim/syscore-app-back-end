<?php

namespace App\Http\Resources;

use App\Models\Coach;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Coach
 */
class CoachResource extends JsonResource
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
            'full_name_ar' => $this->full_name_ar,
            'full_name_en' => $this->full_name_en,
            'photo_url' => $this->photo_url,
            'bio_ar' => $this->bio_ar,
            'bio_en' => $this->bio_en,
            'sport_id' => $this->sport_id,
            'sport' => new SportResource($this->whenLoaded('sport')),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}

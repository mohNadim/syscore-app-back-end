<?php

namespace App\Http\Resources;

use App\Models\BookableUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookableUnit
 */
class BookableUnitResource extends JsonResource
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
            'facility_id' => $this->facility_id,
            'code' => $this->code,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'spaces' => SpaceResource::collection($this->whenLoaded('spaces')),
            'sports' => SportResource::collection($this->whenLoaded('sports')),
        ];
    }
}

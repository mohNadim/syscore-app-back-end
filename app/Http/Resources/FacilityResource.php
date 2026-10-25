<?php

namespace App\Http\Resources;

use App\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Facility
 */
class FacilityResource extends JsonResource
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
            'type' => $this->type,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'spaces' => SpaceResource::collection($this->whenLoaded('spaces')),
            'units' => BookableUnitResource::collection($this->whenLoaded('units')),
        ];
    }
}

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
            'images' => $this->images ?? [],
            'cover_image' => $this->images[0] ?? null,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            $this->mergeWhen($this->relationLoaded('operatingHours'), fn (): array => [
                'opening_hours' => $this->operatingHours
                    ->sortBy('weekday')
                    ->values()
                    ->map(fn ($hours): array => [
                        'weekday' => $hours->weekday,
                        'open_time' => substr((string) $hours->open_time, 0, 5),
                        'close_time' => substr((string) $hours->close_time, 0, 5),
                    ])->all(),
            ]),
            $this->mergeWhen($this->relationLoaded('units') && $this->units->every(fn ($unit): bool => $unit->relationLoaded('priceRules')), fn (): array => [
                'price_from' => $this->units->flatMap->priceRules->min('price_per_hour'),
                'price_unit' => 'hour',
                'currency' => 'SYP',
            ]),
            'spaces' => SpaceResource::collection($this->whenLoaded('spaces')),
            'units' => BookableUnitResource::collection($this->whenLoaded('units')),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Discount;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Discount
 */
class UserDiscountResource extends JsonResource
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
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en ?? $this->name_ar,
            'type' => $this->type,
            'value' => $this->value,
            'target' => $this->target,
            'starts_at' => $timezone->utcToLocalIso($this->starts_at),
            'ends_at' => $timezone->utcToLocalIso($this->ends_at),
            'time_from' => $this->time_from === null ? null : substr($this->time_from, 0, 5),
            'time_to' => $this->time_to === null ? null : substr($this->time_to, 0, 5),
            'weekdays' => $this->weekdayList(),
            'sports' => $this->whenLoaded('sports', fn () => $this->sports->map(fn ($sport): array => ['id' => $sport->id, 'name_ar' => $sport->name_ar, 'name_en' => $sport->name_en])->all()),
            'academies' => $this->whenLoaded('academies', fn () => $this->academies->map(fn ($academy): array => ['id' => $academy->id, 'name_ar' => $academy->name_ar, 'name_en' => $academy->name_en])->all()),
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Discount;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Discount
 */
class DiscountResource extends JsonResource
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
            'name_en' => $this->name_en,
            'type' => $this->type,
            'value' => $this->value,
            'target' => $this->target,
            'starts_at' => $timezone->utcToLocalIso($this->starts_at),
            'ends_at' => $timezone->utcToLocalIso($this->ends_at),
            'time_from' => $this->time_from === null ? null : substr($this->time_from, 0, 5),
            'time_to' => $this->time_to === null ? null : substr($this->time_to, 0, 5),
            'weekdays' => $this->weekdayList(),
            'is_active' => $this->is_active,
            'notify_users' => $this->notify_users,
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($category) => $this->brief($category))->all()),
            'sports' => $this->whenLoaded('sports', fn () => $this->sports->map(fn ($sport) => $this->brief($sport))->all()),
            'academies' => $this->whenLoaded('academies', fn () => $this->academies->map(fn ($academy) => $this->brief($academy))->all()),
            'created_by_staff_id' => $this->created_by_staff_id,
            'created_at' => $this->created_at ? app(DisplayTimezone::class)->utcToLocalIso($this->created_at) : null,
        ];
    }

    /**
     * @return array{id: int, name_ar: string, name_en: string|null}
     */
    private function brief(object $model): array
    {
        return ['id' => $model->id, 'name_ar' => $model->name_ar, 'name_en' => $model->name_en];
    }
}

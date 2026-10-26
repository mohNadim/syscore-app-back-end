<?php

namespace App\Http\Resources;

use App\Models\BookingSeries;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingSeries
 */
class BookingSeriesResource extends JsonResource
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
            'unit_id' => $this->unit_id,
            'unit' => new BookableUnitResource($this->whenLoaded('unit')),
            'sport_id' => $this->sport_id,
            'sport' => new SportResource($this->whenLoaded('sport')),
            'weekday' => $this->weekday,
            'start_time' => substr((string) $this->start_time, 0, 5),
            'duration_minutes' => $this->duration_minutes,
            'first_date' => $this->first_date->toDateString(),
            'last_date' => $this->last_date->toDateString(),
            'status' => $this->status,
            'bookings' => BookingResource::collection($this->whenLoaded('bookings')),
            'bookings_count' => $this->whenCounted('bookings'),
            'created_at' => $this->created_at ? $timezone->utcToLocalIso($this->created_at) : null,
            'cancelled_at' => $this->cancelled_at ? $timezone->utcToLocalIso($this->cancelled_at) : null,
        ];
    }
}

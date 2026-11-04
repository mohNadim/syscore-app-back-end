<?php

namespace App\Http\Resources;

use App\Models\Child;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Child
 */
class ChildResource extends JsonResource
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
            'user' => new UserResource($this->whenLoaded('user')),
            'full_name' => $this->full_name,
            'birth_date' => $this->birth_date->toDateString(),
            'age' => $this->birth_date->age,
            'gender' => $this->gender,
            'health_notes' => $this->health_notes,
            'created_at' => $this->created_at ? app(DisplayTimezone::class)->utcToLocalIso($this->created_at) : null,
            'updated_at' => $this->updated_at ? app(DisplayTimezone::class)->utcToLocalIso($this->updated_at) : null,
        ];
    }
}

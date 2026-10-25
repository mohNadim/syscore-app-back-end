<?php

namespace App\Http\Resources;

use App\Models\Child;
use Illuminate\Http\Request;

/**
 * @mixin Child
 */
class AdminChildResource extends ChildResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'user_id' => $this->user_id,
            'is_active' => $this->is_active,
        ];
    }
}

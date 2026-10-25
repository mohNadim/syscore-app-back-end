<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * @mixin User
 */
class AdminUserResource extends UserResource
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
            'children_count' => $this->whenCounted('children'),
            'children' => AdminChildResource::collection($this->whenLoaded('children')),
            'categories' => UserCategoryResource::collection($this->whenLoaded('categories')),
        ];
    }
}

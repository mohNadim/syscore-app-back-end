<?php

namespace App\Http\Resources;

use App\Models\Staff;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Staff
 */
class StaffResource extends JsonResource
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
            'full_name' => $this->full_name,
            'username' => $this->username,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at ? app(DisplayTimezone::class)->utcToLocalIso($this->last_login_at) : null,
            'created_at' => $this->created_at ? app(DisplayTimezone::class)->utcToLocalIso($this->created_at) : null,
            'role' => $this->whenLoaded('role', fn () => new RoleResource($this->role->withoutRelations())),
            'permissions' => $this->whenLoaded('role', fn () => $this->role->permissions->pluck('code')->values()),
        ];
    }
}

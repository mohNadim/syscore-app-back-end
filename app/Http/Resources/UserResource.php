<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin User
 */
class UserResource extends JsonResource
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
            'phone' => $this->phone,
            'full_name' => $this->full_name,
            'preferred_lang' => $this->preferred_lang,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at ? app(DisplayTimezone::class)->utcToLocalIso($this->last_login_at) : null,
            'created_at' => $this->created_at ? app(DisplayTimezone::class)->utcToLocalIso($this->created_at) : null,
            'assigned_at' => $this->whenPivotLoadedAs('pivot', 'user_category_members', fn () => $this->pivot->assigned_at
                ? app(DisplayTimezone::class)->utcToLocalIso(Carbon::parse($this->pivot->assigned_at, 'UTC'))
                : null),
        ];
    }
}

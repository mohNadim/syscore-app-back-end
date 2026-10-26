<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use App\Services\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
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
            'actor_type' => $this->actor_type,
            'actor' => $this->actorSummary(),
            'action' => $this->action,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'before' => $this->before,
            'after' => $this->after,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at ? $timezone->utcToLocalIso($this->created_at) : null,
        ];
    }

    /**
     * Summarize the actor when it is a loaded user or staff member.
     *
     * @return array{type: string, id: int, name: string|null}|null
     */
    private function actorSummary(): ?array
    {
        if ($this->actor_type === 'user' && $this->relationLoaded('actorUser') && $this->actorUser !== null) {
            return ['type' => 'user', 'id' => $this->actorUser->id, 'name' => $this->actorUser->full_name];
        }

        if ($this->actor_type === 'staff' && $this->relationLoaded('actorStaff') && $this->actorStaff !== null) {
            return ['type' => 'staff', 'id' => $this->actorStaff->id, 'name' => $this->actorStaff->full_name];
        }

        return null;
    }
}

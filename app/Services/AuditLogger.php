<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * Record an audit log entry for an action on an entity.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(string $action, Model $entity, ?array $before = null, ?array $after = null, User|Staff|null $actor = null): AuditLog
    {
        $actor ??= $this->resolveActor();

        return AuditLog::create([
            'actor_type' => match (true) {
                $actor instanceof User => 'user',
                $actor instanceof Staff => 'staff',
                default => 'system',
            },
            'actor_user_id' => $actor instanceof User ? $actor->getKey() : null,
            'actor_staff_id' => $actor instanceof Staff ? $actor->getKey() : null,
            'action' => $action,
            'entity_type' => Str::snake(class_basename($entity)),
            'entity_id' => $entity->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Record an audit log entry from the entity's last saved changes; call after saving.
     * Previous values come from `getPrevious()` because `getOriginal()` is re-synced on save.
     * Hidden attributes (such as password_hash) are excluded.
     */
    public function logChange(string $action, Model $entity, User|Staff|null $actor = null): AuditLog
    {
        $hidden = $entity->getHidden();
        $after = array_diff_key($entity->getChanges(), array_flip($hidden));
        $before = array_intersect_key($entity->getPrevious(), $after);

        return $this->log($action, $entity, $before, $after, $actor);
    }

    /**
     * Resolve the authenticated actor of the current request, if any.
     */
    protected function resolveActor(): User|Staff|null
    {
        $user = auth()->user();

        return $user instanceof User || $user instanceof Staff ? $user : null;
    }
}

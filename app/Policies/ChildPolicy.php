<?php

namespace App\Policies;

use App\Models\Child;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChildPolicy
{
    /**
     * Determine whether the user can view the child.
     */
    public function view(User $user, Child $child): Response
    {
        return $this->ownedActiveChild($user, $child);
    }

    /**
     * Determine whether the user can update the child.
     */
    public function update(User $user, Child $child): Response
    {
        return $this->ownedActiveChild($user, $child);
    }

    /**
     * Determine whether the user can delete the child.
     */
    public function delete(User $user, Child $child): Response
    {
        return $this->ownedActiveChild($user, $child);
    }

    /**
     * Allow only the parent's own, non-archived children; anything else looks like it does not exist.
     */
    private function ownedActiveChild(User $user, Child $child): Response
    {
        return $child->user_id === $user->id && $child->is_active
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}

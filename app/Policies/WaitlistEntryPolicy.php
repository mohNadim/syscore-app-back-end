<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Auth\Access\Response;

class WaitlistEntryPolicy
{
    /**
     * Determine whether the user can remove the waitlist entry of one of their own children.
     */
    public function delete(User $user, WaitlistEntry $waitlistEntry): Response
    {
        return $waitlistEntry->child()->where('user_id', $user->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can accept the offer made to one of their own children.
     */
    public function accept(User $user, WaitlistEntry $waitlistEntry): Response
    {
        return $this->delete($user, $waitlistEntry);
    }
}

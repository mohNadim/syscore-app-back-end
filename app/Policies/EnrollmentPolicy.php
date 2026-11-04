<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EnrollmentPolicy
{
    /**
     * Determine whether the user can view the enrollment.
     */
    public function view(User $user, Enrollment $enrollment): Response
    {
        return $this->owned($user, $enrollment);
    }

    /**
     * Determine whether the user can cancel the enrollment or undo its scheduled cancellation.
     */
    public function cancel(User $user, Enrollment $enrollment): Response
    {
        return $this->owned($user, $enrollment);
    }

    /**
     * Allow only enrollments of the user's own children; anything else looks like it does not exist.
     */
    private function owned(User $user, Enrollment $enrollment): Response
    {
        return $enrollment->child()->where('user_id', $user->id)->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}

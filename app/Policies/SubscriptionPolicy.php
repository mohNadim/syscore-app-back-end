<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SubscriptionPolicy
{
    /**
     * Determine whether the user can view the subscription.
     */
    public function view(User $user, Subscription $subscription): Response
    {
        return $this->owned($user, $subscription);
    }

    /**
     * Determine whether the user can request a renewal of the subscription.
     */
    public function renew(User $user, Subscription $subscription): Response
    {
        return $this->owned($user, $subscription);
    }

    /**
     * Determine whether the user can cancel the subscription.
     */
    public function cancel(User $user, Subscription $subscription): Response
    {
        return $this->owned($user, $subscription);
    }

    /**
     * Allow only subscriptions of the user's own children; anything else looks like it does not exist.
     */
    private function owned(User $user, Subscription $subscription): Response
    {
        return $subscription->enrollment()->whereHas('child', fn ($child) => $child->where('user_id', $user->id))->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}

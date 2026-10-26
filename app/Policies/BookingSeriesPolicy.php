<?php

namespace App\Policies;

use App\Models\BookingSeries;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookingSeriesPolicy
{
    /**
     * Determine whether the user can view the series.
     */
    public function view(User $user, BookingSeries $bookingSeries): Response
    {
        return $this->owned($user, $bookingSeries);
    }

    /**
     * Determine whether the user can cancel the series.
     */
    public function cancel(User $user, BookingSeries $bookingSeries): Response
    {
        return $this->owned($user, $bookingSeries);
    }

    /**
     * Allow only the customer's own series; anything else looks like it does not exist.
     */
    private function owned(User $user, BookingSeries $bookingSeries): Response
    {
        return $bookingSeries->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}

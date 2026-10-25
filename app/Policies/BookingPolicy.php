<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookingPolicy
{
    /**
     * Determine whether the user can view the booking.
     */
    public function view(User $user, Booking $booking): Response
    {
        return $this->owned($user, $booking);
    }

    /**
     * Determine whether the user can cancel the booking.
     */
    public function cancel(User $user, Booking $booking): Response
    {
        return $this->owned($user, $booking);
    }

    /**
     * Allow only the customer's own bookings; anything else looks like it does not exist.
     */
    private function owned(User $user, Booking $booking): Response
    {
        return $booking->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}

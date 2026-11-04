<?php

return [

    'read' => 'Notification marked as read.',
    'read_all' => 'All notifications marked as read.',

    'events' => [
        'booking_confirmed' => [
            'title' => 'Your booking is confirmed',
            'body' => 'Your booking :booking_number at :unit on :time is confirmed.',
        ],
        'payment_succeeded' => [
            'title' => 'Payment received',
            'body' => 'Your payment of :amount SYP was received successfully.',
        ],
        'booking_reminder' => [
            'title' => 'Booking reminder',
            'body' => 'Your booking at :unit starts soon (:time).',
        ],
        'child_absent' => [
            'title' => 'Child marked absent',
            'body' => ':child was marked absent from the session on :time.',
        ],
        'subscription_expiring' => [
            'title' => 'Subscription ending soon',
            'body' => 'The subscription of :child in group :group ends on :date.',
        ],
        'subscription_expired' => [
            'title' => 'Subscription expired',
            'body' => 'The subscription of :child in group :group has expired. Renew to continue.',
        ],
        'booking_cancelled' => [
            'title' => 'Booking cancelled',
            'body' => 'Your booking :booking_number at :unit on :time was cancelled.',
        ],
        'booking_expired' => [
            'title' => 'Booking expired',
            'body' => 'Your booking :booking_number at :unit on :time expired because the deposit was not paid in time.',
        ],
        'waitlist_offered' => [
            'title' => 'A seat is available',
            'body' => 'A seat opened for :child in group :group. Enroll before :date or the offer expires.',
        ],
        'waitlist_offer_expired' => [
            'title' => 'Seat offer expired',
            'body' => 'The seat offer for :child in group :group has expired.',
        ],
        'enrollment_ended' => [
            'title' => 'Enrollment ended',
            'body' => 'The enrollment of :child in group :group has ended.',
        ],
        'subscription_unpaid_expired' => [
            'title' => 'Subscription cancelled',
            'body' => 'The unpaid subscription of :child in group :group was cancelled because payment was not received.',
        ],
        'session_cancelled' => [
            'title' => 'Session cancelled',
            'body' => 'The session of :child in group :group scheduled on :time was cancelled.',
        ],
        'session_updated' => [
            'title' => 'Session changed',
            'body' => 'The session of :child in group :group was changed. New time: :time at :unit.',
        ],
        'discount_created' => [
            'title' => 'New discount',
            'body' => 'A new discount is available: :name.',
        ],
    ],

    'validation' => [
        'unread_invalid' => 'The unread filter must be true or false.',
        'per_page_invalid' => 'The page size must be a whole number between 1 and 100.',
    ],

];

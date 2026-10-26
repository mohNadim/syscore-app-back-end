<?php

return [

    'created' => 'Booking created successfully.',
    'cancelled' => 'Booking cancelled successfully.',
    'no_show' => 'Booking marked as no-show.',
    'cannot_mark_no_show' => 'Only a confirmed booking that has already started can be marked as no-show.',

    'validation' => [
        'date_invalid' => 'The date must use the YYYY-MM-DD format.',
        'to_after_from' => 'The end date cannot be before the start date.',
        'status_invalid' => 'The status must be one of: pending_deposit, confirmed, completed, cancelled, expired, no_show.',
        'unit_id_invalid' => 'The unit must be the id of an existing bookable unit.',
        'facility_id_invalid' => 'The facility filter must be a whole number.',
        'user_id_invalid' => 'The user must be the id of an existing user.',
        'phone_filter_invalid' => 'The phone filter must be text of at most 20 characters.',
        'booking_number_invalid' => 'The booking number filter must be text of at most 20 characters.',
        'per_page_invalid' => 'The page size must be a whole number between 1 and 100.',
        'sport_id_invalid' => 'The sport is required and must be the id of an existing sport.',
        'starts_at_invalid' => 'The start is required in YYYY-MM-DD HH:MM format.',
        'duration_invalid' => 'The duration must be a whole number of minutes between 1 and 600.',
        'guest_name_required' => 'The guest name is required when no user is selected.',
        'guest_name_invalid' => 'The guest name must be between 2 and 150 characters.',
        'guest_phone_required' => 'The guest phone is required when no user is selected.',
        'guest_phone_invalid' => 'The guest phone must be a valid Syrian mobile number, e.g. +963912345678.',
        'payment_mode_invalid' => 'The payment mode must be one of: cash_full, deposit_then_cash, online_full.',
        'reason_required' => 'The cancellation reason is required.',
        'reason_invalid' => 'The cancellation reason must be text of at most 255 characters.',
    ],

];

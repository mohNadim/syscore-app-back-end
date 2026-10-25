<?php

return [

    'created' => 'Recurring booking created successfully.',
    'cancelled' => 'Recurring booking cancelled successfully.',
    'some_unavailable' => 'Some of the requested dates are not available.',
    'none_available' => 'None of the requested dates is available.',
    'not_cancellable' => 'This recurring booking is already cancelled.',

    'validation' => [
        'unit_id_invalid' => 'The unit must be the id of an existing bookable unit.',
        'sport_id_invalid' => 'The sport is required and must be the id of an existing sport.',
        'start_time_invalid' => 'The start time is required in HH:MM format.',
        'duration_invalid' => 'The duration must be a whole number of minutes between 1 and 600.',
        'date_invalid' => 'The date is required in YYYY-MM-DD format.',
        'last_before_first' => 'The last date cannot be before the first date.',
        'first_date_past' => 'The first date cannot be in the past.',
        'outside_booking_window' => 'The last date is too far ahead. Please choose an earlier date.',
        'payment_mode_invalid' => 'The payment mode must be one of: online_full, deposit_then_cash.',
        'skip_unavailable_invalid' => 'The skip option must be true or false.',
        'per_page_invalid' => 'The page size must be a whole number between 1 and 100.',
        'reason_invalid' => 'The reason must be text of at most 255 characters.',
    ],

];

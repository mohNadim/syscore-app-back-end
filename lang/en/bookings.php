<?php

return [

    'created' => 'Booking created successfully.',
    'cancelled' => 'Booking cancelled successfully.',
    'slot_taken' => 'This time was just booked by someone else. Please choose another time.',
    'not_cancellable' => 'This booking can no longer be cancelled.',
    'cancellation_deadline_passed' => 'The cancellation deadline has passed. Please contact the administration.',
    'number_generation_failed' => 'The booking number could not be generated. Please try again.',

    'validation' => [
        'unit_id_invalid' => 'The unit must be the id of an existing bookable unit.',
        'unit_unavailable' => 'This unit is not available for booking.',
        'sport_id_invalid' => 'The sport is required and must be the id of an existing sport.',
        'sport_not_offered' => 'This unit does not offer the selected sport.',
        'starts_at_invalid' => 'The start is required in YYYY-MM-DD HH:MM format.',
        'duration_invalid' => 'The duration must be a whole number of minutes between 1 and 600.',
        'duration_not_multiple' => 'The duration must be a multiple of :minutes minutes and at least :minutes minutes.',
        'payment_mode_invalid' => 'The payment mode must be one of: online_full, deposit_then_cash.',
        'past_start' => 'The booking start must be in the future.',
        'outside_booking_window' => 'The booking is too far ahead. Please choose an earlier date.',
        'outside_operating_hours' => 'The booking must be inside the facility operating hours for that day.',
        'closed' => 'The facility or one of its spaces is closed at that time.',
        'status_invalid' => 'The status must be one of: pending_deposit, confirmed, completed, cancelled, expired, no_show.',
        'scope_invalid' => 'The scope must be upcoming or past.',
        'per_page_invalid' => 'The page size must be a whole number between 1 and 100.',
        'reason_invalid' => 'The reason must be text of at most 255 characters.',
    ],

];

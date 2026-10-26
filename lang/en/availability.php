<?php

return [

    'validation' => [
        'facility_id_invalid' => 'The facility must be the id of an existing facility.',
        'facility_unavailable' => 'This facility is not available for booking.',
        'date_invalid' => 'The date is required in YYYY-MM-DD format.',
        'outside_booking_window' => 'The date must be between today and the last day open for booking.',
        'sport_id_invalid' => 'The sport must be the id of an existing sport.',
        'duration_invalid' => 'The duration must be a whole number of minutes between 1 and 600.',
        'duration_not_multiple' => 'The duration must be a multiple of :minutes minutes and at least :minutes minutes.',
    ],

];

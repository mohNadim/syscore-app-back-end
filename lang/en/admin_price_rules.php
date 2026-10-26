<?php

return [

    'created' => 'Price rule created successfully.',
    'updated' => 'Price rule updated successfully.',
    'deleted' => 'Price rule deleted successfully.',

    'validation' => [
        'unit_id_invalid' => 'The unit must be a valid bookable unit id.',
        'weekday_invalid' => 'The weekday must be a whole number from 0 (Sunday) to 6 (Saturday).',
        'start_time_invalid' => 'The start time is required in HH:MM format.',
        'end_time_invalid' => 'The end time is required in HH:MM format.',
        'end_after_start' => 'The end time must be later than the start time on the same day.',
        'time_step' => 'The times must fall on a half-hour boundary (minutes 00 or 30).',
        'is_peak_invalid' => 'The peak flag must be true or false.',
        'price_invalid' => 'The price per hour must be a whole number between 0 and 100,000,000.',
        'valid_from_invalid' => 'The start of validity is required in YYYY-MM-DD format.',
        'valid_to_invalid' => 'The end of validity must be in YYYY-MM-DD format.',
        'valid_to_after_from' => 'The end of validity must not be before its start.',
        'date_invalid' => 'The date must be in YYYY-MM-DD format.',
        'overlap' => 'This rule overlaps price rule #:id of the same unit in weekday, time and validity period.',
    ],

];

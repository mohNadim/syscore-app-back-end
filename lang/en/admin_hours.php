<?php

return [

    'hours_updated' => 'Operating hours updated successfully.',
    'closure_created' => 'Closure created successfully.',
    'closure_updated' => 'Closure updated successfully.',
    'closure_deleted' => 'Closure deleted successfully.',

    'validation' => [
        'hours_invalid' => 'The hours must be a list of at most 7 days.',
        'weekday_invalid' => 'The weekday must be a whole number from 0 (Sunday) to 6 (Saturday).',
        'weekday_duplicate' => 'Each weekday can appear only once.',
        'time_invalid' => 'The opening and closing times are required in HH:MM format.',
        'close_after_open' => 'The closing time must be later than the opening time on the same day.',
        'target_required' => 'A closure needs either a facility or a space.',
        'target_exclusive' => 'A closure applies to either a facility or a space, not both.',
        'facility_id_invalid' => 'The facility must be a valid facility id.',
        'space_id_invalid' => 'The space must be a valid space id.',
        'starts_at_invalid' => 'The start is required in YYYY-MM-DD HH:MM format.',
        'ends_at_invalid' => 'The end is required in YYYY-MM-DD HH:MM format.',
        'ends_after_starts' => 'The end must be later than the start.',
        'reason_ar_invalid' => 'The Arabic reason must be text of at most 255 characters.',
        'reason_en_invalid' => 'The English reason must be text of at most 255 characters.',
        'date_invalid' => 'The date must be in YYYY-MM-DD format.',
        'to_after_from' => 'The end date must not be before the start date.',
        'upcoming_invalid' => 'The upcoming flag must be true or false.',
        'per_page_invalid' => 'The page size must be a whole number between 1 and 100.',
    ],

];

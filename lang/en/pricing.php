<?php

return [

    'no_price' => 'No price is defined for the requested time.',

    'validation' => [
        'unit_id_invalid' => 'The unit must be the id of an existing bookable unit.',
        'unit_unavailable' => 'This unit is not available for booking.',
        'sport_id_invalid' => 'The sport must be the id of an existing sport.',
        'sport_not_offered' => 'This unit does not offer the selected sport.',
        'starts_at_invalid' => 'The start is required in YYYY-MM-DD HH:MM format.',
        'duration_invalid' => 'The duration must be a whole number of minutes between 1 and 600.',
        'duration_not_multiple' => 'The duration must be a multiple of :minutes minutes and at least :minutes minutes.',
    ],

];

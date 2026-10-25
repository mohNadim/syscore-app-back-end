<?php

return [

    'coach_created' => 'Coach created successfully.',
    'coach_updated' => 'Coach updated successfully.',
    'coach_deleted' => 'Coach deleted successfully.',
    'coach_in_use' => 'This coach is still linked to academy groups or training sessions. Deactivate the coach (is_active = false) instead of deleting.',

    'validation' => [
        'full_name_ar_required' => 'The Arabic name is required.',
        'full_name_ar_invalid' => 'The Arabic name must be text of at most 150 characters.',
        'full_name_en_invalid' => 'The English name must be text of at most 150 characters.',
        'bio_invalid' => 'The bio must be text of at most 5000 characters.',
        'sport_id_invalid' => 'The sport must be an existing sport.',
        'is_active_invalid' => 'The active flag must be true or false.',
        'sort_order_invalid' => 'The sort order must be a whole number between 0 and 65535.',
        'photo_invalid' => 'The photo must be a JPG, PNG or WEBP image of at most 2 MB.',
        'remove_photo_invalid' => 'The remove-photo flag must be true or false.',
    ],

];

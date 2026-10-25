<?php

return [

    'sport_created' => 'Sport created successfully.',
    'sport_updated' => 'Sport updated successfully.',
    'sport_deleted' => 'Sport deleted successfully.',
    'sport_in_use' => 'This sport is still linked to coaches, academies, bookable units, bookings or discounts. Deactivate it (is_active = false) instead of deleting it.',

    'validation' => [
        'name_ar_required' => 'The Arabic name is required.',
        'name_ar_invalid' => 'The Arabic name must be text of at most 100 characters.',
        'name_ar_taken' => 'A sport with this Arabic name already exists.',
        'name_en_invalid' => 'The English name must be text of at most 100 characters.',
        'icon_url_invalid' => 'The icon URL must be a valid URL of at most 500 characters.',
        'is_active_invalid' => 'The active flag must be true or false.',
    ],

];

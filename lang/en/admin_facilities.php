<?php

return [

    'facility_created' => 'Facility created successfully.',
    'facility_updated' => 'Facility updated successfully.',
    'facility_deleted' => 'Facility deleted successfully.',
    'facility_in_use' => 'This facility still has spaces, units, operating hours or closures. Remove them first, or deactivate the facility instead of deleting it.',
    'space_created' => 'Space created successfully.',
    'space_updated' => 'Space updated successfully.',
    'space_deleted' => 'Space deleted successfully.',
    'space_in_use' => 'This space is linked to units, reservations or closures. Remove those links first.',
    'unit_created' => 'Bookable unit created successfully.',
    'unit_updated' => 'Bookable unit updated successfully.',
    'unit_deleted' => 'Bookable unit deleted successfully.',
    'unit_sports_updated' => 'Unit sports updated successfully.',
    'unit_in_use' => 'This unit has bookings, price rules, schedules, sessions or booking series. Deactivate it instead of deleting it.',

    'validation' => [
        'type_invalid' => 'The facility type must be either pitch or hall.',
        'name_ar_required' => 'The Arabic name is required.',
        'name_ar_invalid' => 'The Arabic name must be text of at most 100 characters.',
        'name_en_invalid' => 'The English name must be text of at most 100 characters.',
        'description_ar_invalid' => 'The Arabic description must be text of at most 5000 characters.',
        'description_en_invalid' => 'The English description must be text of at most 5000 characters.',
        'sort_order_invalid' => 'The sort order must be a whole number between 0 and 65535.',
        'is_active_invalid' => 'The active flag must be true or false.',
        'space_code_invalid' => 'The space code is required and must be at most 20 characters of capital letters, digits or underscores.',
        'space_code_taken' => 'This space code is already used in this facility.',
        'facility_id_invalid' => 'The facility must be a valid facility id.',
        'unit_code_invalid' => 'The unit code is required and must be at most 20 characters of capital letters, digits or underscores.',
        'unit_code_taken' => 'This unit code is already used.',
        'space_ids_required' => 'At least one space is required.',
        'space_ids_invalid' => 'The spaces must be a list of space ids.',
        'space_id_invalid' => 'Each space must be a valid space id without duplicates.',
        'space_ids_facility' => 'All spaces must belong to the selected facility.',
        'sport_ids_required' => 'The sports list is required.',
        'sport_ids_invalid' => 'The sports must be a list of sport ids.',
        'sport_id_invalid' => 'Each sport must be a valid sport id without duplicates.',
    ],

];

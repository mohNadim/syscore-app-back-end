<?php

return [

    'created' => 'Discount created successfully.',
    'updated' => 'Discount updated successfully.',
    'deleted' => 'Discount deleted successfully.',
    'discount_in_use' => 'This discount was already applied to bookings or subscriptions. Deactivate it (is_active = false) instead of deleting it.',

    'validation' => [
        'name_ar_required' => 'The Arabic name is required.',
        'name_ar_invalid' => 'The Arabic name must be text of at most 150 characters.',
        'name_en_invalid' => 'The English name must be text of at most 150 characters.',
        'type_invalid' => 'The type must be percent or fixed.',
        'value_invalid' => 'The value must be a whole number of at least 1.',
        'value_percent_range' => 'A percentage discount must be between 1 and 100.',
        'value_fixed_range' => 'A fixed discount must be between 1 and 100,000,000 SYP.',
        'target_invalid' => 'The target must be booking, subscription or all.',
        'starts_at_invalid' => 'The start is required in YYYY-MM-DD HH:MM format.',
        'ends_at_invalid' => 'The end is required in YYYY-MM-DD HH:MM format.',
        'ends_after_starts' => 'The end must be later than the start.',
        'time_from_invalid' => 'The start of the daily window must be in HH:MM format.',
        'time_to_invalid' => 'The end of the daily window must be in HH:MM format.',
        'time_window_incomplete' => 'The daily window needs both a start and an end time, or neither.',
        'time_to_after_from' => 'The end of the daily window must be later than its start.',
        'weekdays_invalid' => 'The weekdays must be a list of distinct numbers from 0 (Sunday) to 6 (Saturday).',
        'is_active_invalid' => 'The active flag must be true or false.',
        'notify_users_invalid' => 'The notify flag must be true or false.',
        'category_ids_invalid' => 'The categories must be a list of distinct existing user category ids.',
        'sport_ids_invalid' => 'The sports must be a list of distinct existing sport ids.',
        'academy_ids_invalid' => 'The academies must be a list of distinct existing academy ids.',
        'active_at_invalid' => 'The date must be in YYYY-MM-DD HH:MM format.',
        'per_page_invalid' => 'The page size must be a whole number from 1 to 100.',
    ],

];

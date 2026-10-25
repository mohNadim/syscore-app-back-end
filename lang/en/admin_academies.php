<?php

return [

    'academy_created' => 'Academy created successfully.',
    'academy_updated' => 'Academy updated successfully.',
    'academy_deleted' => 'Academy deleted successfully.',
    'academy_in_use' => 'This academy still has groups or discounts linked to it. Deactivate it (is_active = false) instead of deleting it.',
    'group_created' => 'Group created successfully.',
    'group_updated' => 'Group updated successfully.',
    'group_deleted' => 'Group deleted successfully.',
    'group_in_use' => 'This group still has schedules, sessions, enrollments or waitlist entries. Deactivate it (is_active = false) instead of deleting it.',

    'validation' => [
        'sport_id_required' => 'The sport is required.',
        'sport_id_invalid' => 'The sport must be an existing sport.',
        'name_ar_required' => 'The Arabic name is required.',
        'name_ar_invalid' => 'The Arabic name must be text of at most 150 characters.',
        'name_en_invalid' => 'The English name must be text of at most 150 characters.',
        'description_invalid' => 'The description must be text of at most 5000 characters.',
        'is_active_invalid' => 'The active flag must be true or false.',
        'cover_invalid' => 'The cover must be a JPG, PNG or WEBP image of at most 2 MB.',
        'remove_cover_invalid' => 'The remove-cover flag must be true or false.',
        'coach_id_invalid' => 'The coach must be an existing coach.',
        'age_min_required' => 'The minimum age is required.',
        'age_min_invalid' => 'The minimum age must be a whole number between 0 and 30.',
        'age_max_required' => 'The maximum age is required.',
        'age_max_invalid' => 'The maximum age must be a whole number between 0 and 30.',
        'age_max_below_min' => 'The maximum age cannot be lower than the minimum age.',
        'gender_required' => 'The gender is required.',
        'gender_invalid' => 'The gender must be male, female or mixed.',
        'capacity_required' => 'The capacity is required.',
        'capacity_invalid' => 'The capacity must be a whole number between 1 and 500.',
        'capacity_below_enrolled' => 'The capacity cannot be lower than the :count children currently enrolled.',
        'level_invalid' => 'The level must be text of at most 50 characters.',
        'monthly_price_required' => 'The monthly price is required.',
        'monthly_price_invalid' => 'The monthly price must be a whole number between 0 and 100000000.',
        'subscription_days_invalid' => 'The subscription length must be a whole number of days between 1 and 365.',
    ],

];

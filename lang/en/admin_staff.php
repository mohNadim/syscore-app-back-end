<?php

return [

    'created' => 'Staff member created successfully.',
    'updated' => 'Staff member updated successfully.',
    'deactivated' => 'Staff member deactivated successfully.',
    'cannot_modify_self' => 'You cannot deactivate your own account or change your own role.',
    'cannot_deactivate_self' => 'You cannot deactivate your own account.',

    'validation' => [
        'full_name_required' => 'The full name is required.',
        'full_name_invalid' => 'The full name must be text.',
        'full_name_length' => 'The full name must be between 2 and 150 characters.',
        'username_required' => 'The username is required.',
        'username_invalid' => 'The username must be text.',
        'username_length' => 'The username must be between 3 and 100 characters.',
        'username_format' => 'The username may only contain letters, numbers, dots, underscores and hyphens.',
        'username_taken' => 'This username is already taken.',
        'phone_invalid' => 'The phone number must be text of at most 20 characters.',
        'password_required' => 'The password is required.',
        'password_invalid' => 'The password must be text.',
        'password_min' => 'The password must be at least 8 characters.',
        'password_confirmed' => 'The password confirmation does not match.',
        'role_id_required' => 'The role is required.',
        'role_id_invalid' => 'The role must be a valid role id.',
        'is_active_invalid' => 'The active flag must be true or false.',
        'search_invalid' => 'The search term must be text of at most 100 characters.',
        'per_page_invalid' => 'The page size must be a number between 1 and 100.',
    ],

];

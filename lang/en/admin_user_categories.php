<?php

return [

    'created' => 'User category created successfully.',
    'updated' => 'User category updated successfully.',
    'deleted' => 'User category deleted successfully.',
    'category_in_use' => 'This category still has members or is used by discounts. Deactivate it (is_active = false) instead of deleting it.',
    'members_added' => 'Members added to the category.',
    'member_removed' => 'Member removed from the category.',

    'validation' => [
        'name_ar_required' => 'The Arabic name is required.',
        'name_ar_invalid' => 'The Arabic name must be text of at most 100 characters.',
        'name_en_invalid' => 'The English name must be text of at most 100 characters.',
        'description_invalid' => 'The description must be text of at most 1000 characters.',
        'is_active_invalid' => 'The active flag must be true or false.',
        'per_page_invalid' => 'The page size must be a whole number from 1 to 100.',
        'user_ids_invalid' => 'The user ids must be a list of 1 to 500 users.',
        'user_id_invalid' => 'Each user id must be the id of an existing user.',
        'user_id_duplicate' => 'The user ids must not contain duplicates.',
    ],

];

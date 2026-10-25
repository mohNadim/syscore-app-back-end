<?php

return [

    'updated' => 'User updated successfully.',
    'child_updated' => 'Child updated successfully.',
    'categories_synced' => 'User categories updated successfully.',

    'validation' => [
        'search_invalid' => 'The search term must be text of at most 100 characters.',
        'is_active_invalid' => 'The active flag must be true or false.',
        'category_id_invalid' => 'The category must be an existing user category.',
        'category_id_duplicate' => 'The category ids must not contain duplicates.',
        'category_ids_invalid' => 'The category ids must be a list of at most 100 categories (it may be empty).',
        'per_page_invalid' => 'The page size must be a whole number from 1 to 100.',
    ],

];

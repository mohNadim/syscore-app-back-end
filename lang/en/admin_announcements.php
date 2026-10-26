<?php

return [

    'announcement_sent' => 'Announcement sent successfully.',

    'validation' => [
        'audience_required' => 'The audience is required.',
        'audience_invalid' => 'The audience must be one of all, academy, category or users.',
        'academy_id_required' => 'The academy is required when the audience is academy.',
        'academy_id_invalid' => 'The selected academy does not exist.',
        'academy_id_filter_invalid' => 'The academy filter must be a whole number of at least 1.',
        'user_category_id_required' => 'The user category is required when the audience is category.',
        'user_category_id_invalid' => 'The selected user category does not exist or is inactive.',
        'user_category_id_filter_invalid' => 'The user category filter must be a whole number of at least 1.',
        'user_ids_required' => 'The users list is required when the audience is users.',
        'user_ids_invalid' => 'The users list must contain between 1 and 200 user ids.',
        'user_ids_item_invalid' => 'Each user must be a distinct, existing user id.',
        'title_ar_required' => 'The Arabic title is required.',
        'title_ar_invalid' => 'The Arabic title must be text of at most 200 characters.',
        'title_en_invalid' => 'The English title must be text of at most 200 characters.',
        'body_ar_required' => 'The Arabic body is required.',
        'body_ar_invalid' => 'The Arabic body must be text of at most 5000 characters.',
        'body_en_invalid' => 'The English body must be text of at most 5000 characters.',
        'per_page_invalid' => 'The per-page value must be a whole number between 1 and 50.',
    ],

];

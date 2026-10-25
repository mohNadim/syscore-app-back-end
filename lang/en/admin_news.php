<?php

return [

    'news_created' => 'News article created successfully.',
    'news_updated' => 'News article updated successfully.',
    'news_deleted' => 'News article deleted successfully.',

    'validation' => [
        'title_ar_required' => 'The Arabic title is required.',
        'title_ar_invalid' => 'The Arabic title must be text of at most 200 characters.',
        'title_en_required' => 'The English title is required.',
        'title_en_invalid' => 'The English title must be text of at most 200 characters.',
        'body_ar_required' => 'The Arabic body is required.',
        'body_ar_invalid' => 'The Arabic body must be text of at most 20000 characters.',
        'body_en_required' => 'The English body is required.',
        'body_en_invalid' => 'The English body must be text of at most 20000 characters.',
        'cover_url_invalid' => 'The cover URL must be a valid http or https URL of at most 500 characters.',
        'is_published_invalid' => 'The published flag must be true or false.',
        'published_at_invalid' => 'The publish date must be a valid date.',
        'per_page_invalid' => 'The per-page value must be a whole number between 1 and 50.',
    ],

];

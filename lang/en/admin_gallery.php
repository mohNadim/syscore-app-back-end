<?php

return [

    'album_created' => 'Gallery album created successfully.',
    'album_updated' => 'Gallery album updated successfully.',
    'album_deleted' => 'Gallery album and its photos deleted successfully.',
    'photo_created' => 'Photo added successfully.',
    'photo_updated' => 'Photo updated successfully.',
    'photo_deleted' => 'Photo deleted successfully.',

    'validation' => [
        'title_ar_required' => 'The Arabic album title is required.',
        'title_ar_invalid' => 'The Arabic album title must be text of at most 200 characters.',
        'title_en_invalid' => 'The English album title must be text of at most 200 characters.',
        'sort_order_invalid' => 'The sort order must be a whole number between 0 and 65535.',
        'is_published_invalid' => 'The published flag must be true or false.',
        'image_url_required' => 'The image URL is required.',
        'image_url_invalid' => 'The image URL must be a valid http or https URL of at most 500 characters.',
        'caption_ar_invalid' => 'The Arabic caption must be text of at most 255 characters.',
        'caption_en_invalid' => 'The English caption must be text of at most 255 characters.',
        'per_page_invalid' => 'The per-page value must be a whole number between 1 and 50.',
    ],

];

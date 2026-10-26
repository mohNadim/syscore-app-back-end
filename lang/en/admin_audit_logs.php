<?php

return [

    'attributes' => [
        'actor_type' => 'actor type',
        'actor_user_id' => 'actor user',
        'actor_staff_id' => 'actor staff member',
        'action' => 'action',
        'entity_type' => 'entity type',
        'entity_id' => 'entity',
        'date_from' => 'start date',
        'date_to' => 'end date',
    ],

    'validation' => [
        'actor_type_invalid' => 'The actor type must be one of: user, staff, system.',
        'actor_user_id_invalid' => 'The actor user filter must be a whole number of at least 1.',
        'actor_staff_id_invalid' => 'The actor staff filter must be a whole number of at least 1.',
        'action_invalid' => 'The action filter must be text of at most 100 characters.',
        'entity_type_invalid' => 'The entity type filter must be text of at most 50 characters.',
        'entity_id_invalid' => 'The entity filter must be a whole number of at least 1.',
        'date_invalid' => 'The date must use the YYYY-MM-DD format.',
        'date_to_after_from' => 'The end date cannot be before the start date.',
    ],

];

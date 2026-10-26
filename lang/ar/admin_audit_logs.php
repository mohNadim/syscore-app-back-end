<?php

return [

    'attributes' => [
        'actor_type' => 'نوع الفاعل',
        'actor_user_id' => 'المستخدم الفاعل',
        'actor_staff_id' => 'الموظف الفاعل',
        'action' => 'الإجراء',
        'entity_type' => 'نوع الكيان',
        'entity_id' => 'الكيان',
        'date_from' => 'تاريخ البداية',
        'date_to' => 'تاريخ النهاية',
    ],

    'validation' => [
        'actor_type_invalid' => 'يجب أن يكون نوع الفاعل أحد: user, staff, system.',
        'actor_user_id_invalid' => 'يجب أن يكون فلتر المستخدم الفاعل عددًا صحيحًا لا يقل عن 1.',
        'actor_staff_id_invalid' => 'يجب أن يكون فلتر الموظف الفاعل عددًا صحيحًا لا يقل عن 1.',
        'action_invalid' => 'يجب أن يكون فلتر الإجراء نصًا لا يتجاوز 100 حرف.',
        'entity_type_invalid' => 'يجب أن يكون فلتر نوع الكيان نصًا لا يتجاوز 50 حرفًا.',
        'entity_id_invalid' => 'يجب أن يكون فلتر الكيان عددًا صحيحًا لا يقل عن 1.',
        'date_invalid' => 'يجب أن يكون التاريخ بصيغة YYYY-MM-DD.',
        'date_to_after_from' => 'لا يمكن أن يسبق تاريخ النهاية تاريخ البداية.',
    ],

];

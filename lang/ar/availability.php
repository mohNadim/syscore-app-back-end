<?php

return [

    'validation' => [
        'facility_id_invalid' => 'المنشأة يجب أن تكون معرّف منشأة موجودة.',
        'facility_unavailable' => 'هذه المنشأة غير متاحة للحجز.',
        'date_invalid' => 'التاريخ مطلوب بصيغة YYYY-MM-DD.',
        'outside_booking_window' => 'التاريخ يجب أن يكون بين اليوم وآخر يوم متاح للحجز.',
        'sport_id_invalid' => 'الرياضة يجب أن تكون معرّف رياضة موجودة.',
        'limit_invalid' => 'الحد الأقصى يجب أن يكون عدداً صحيحاً من 1 إلى 50.',
        'duration_invalid' => 'المدة يجب أن تكون عدداً صحيحاً من الدقائق بين 1 و 600.',
        'duration_not_multiple' => 'المدة يجب أن تكون من مضاعفات :minutes دقيقة وألا تقل عن :minutes دقيقة.',
    ],

];

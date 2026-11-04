<?php

return [

    'created' => 'تم إنشاء الحجز المتكرر بنجاح.',
    'cancelled' => 'تم إلغاء الحجز المتكرر بنجاح.',
    'some_unavailable' => 'بعض التواريخ المطلوبة غير متاحة.',
    'none_available' => 'لا يوجد أي تاريخ متاح من التواريخ المطلوبة.',
    'not_cancellable' => 'هذا الحجز المتكرر ملغى مسبقاً.',

    'validation' => [
        'unit_id_invalid' => 'الوحدة يجب أن تكون معرّف وحدة قابلة للحجز موجودة.',
        'sport_id_invalid' => 'الرياضة مطلوبة ويجب أن تكون معرّف رياضة موجودة.',
        'start_time_invalid' => 'وقت البداية مطلوب بصيغة HH:MM.',
        'duration_invalid' => 'المدة يجب أن تكون عدداً صحيحاً من الدقائق بين 1 و 600.',
        'date_invalid' => 'التاريخ مطلوب بصيغة YYYY-MM-DD.',
        'last_before_first' => 'تاريخ النهاية لا يمكن أن يسبق تاريخ البداية.',
        'first_date_past' => 'تاريخ البداية لا يمكن أن يكون في الماضي.',
        'outside_booking_window' => 'تاريخ النهاية بعيد جداً. يرجى اختيار تاريخ أقرب.',
        'payment_mode_invalid' => 'طريقة الدفع يجب أن تكون إحدى القيم: online_full، deposit_then_cash، cash_only.',
        'skip_unavailable_invalid' => 'خيار التخطي يجب أن يكون true أو false.',
        'per_page_invalid' => 'حجم الصفحة يجب أن يكون عدداً صحيحاً بين 1 و 100.',
        'reason_invalid' => 'السبب يجب أن يكون نصاً لا يزيد عن 255 حرفاً.',
    ],

];

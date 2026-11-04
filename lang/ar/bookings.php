<?php

return [

    'created' => 'تم إنشاء الحجز بنجاح.',
    'cancelled' => 'تم إلغاء الحجز بنجاح.',
    'payment_option_disabled' => 'خيار الدفع هذا غير متاح حالياً.',
    'slot_taken' => 'تم حجز هذا الوقت للتو من قبل شخص آخر. يرجى اختيار وقت آخر.',
    'not_cancellable' => 'لا يمكن إلغاء هذا الحجز بعد الآن.',
    'cancellation_deadline_passed' => 'انتهت مهلة الإلغاء، تواصل مع الإدارة.',
    'number_generation_failed' => 'تعذر إنشاء رقم الحجز. يرجى المحاولة مرة أخرى.',

    'validation' => [
        'unit_id_invalid' => 'الوحدة يجب أن تكون معرّف وحدة قابلة للحجز موجودة.',
        'unit_unavailable' => 'هذه الوحدة غير متاحة للحجز.',
        'sport_id_invalid' => 'الرياضة مطلوبة ويجب أن تكون معرّف رياضة موجودة.',
        'sport_not_offered' => 'هذه الوحدة لا تقدّم الرياضة المختارة.',
        'starts_at_invalid' => 'بداية الحجز مطلوبة بصيغة YYYY-MM-DD HH:MM.',
        'duration_invalid' => 'المدة يجب أن تكون عدداً صحيحاً من الدقائق بين 1 و 600.',
        'duration_not_multiple' => 'المدة يجب أن تكون من مضاعفات :minutes دقيقة وألا تقل عن :minutes دقيقة.',
        'payment_mode_invalid' => 'طريقة الدفع يجب أن تكون إحدى القيم: online_full، deposit_then_cash، cash_only.',
        'past_start' => 'بداية الحجز يجب أن تكون في المستقبل.',
        'outside_booking_window' => 'موعد الحجز بعيد جداً. يرجى اختيار تاريخ أقرب.',
        'outside_operating_hours' => 'الحجز يجب أن يكون ضمن ساعات عمل المنشأة لذلك اليوم.',
        'closed' => 'المنشأة أو أحد ملاعبها مغلق في هذا الوقت.',
        'status_invalid' => 'الحالة يجب أن تكون إحدى القيم: pending_deposit، confirmed، completed، cancelled، expired، no_show.',
        'scope_invalid' => 'النطاق يجب أن يكون upcoming أو past.',
        'per_page_invalid' => 'حجم الصفحة يجب أن يكون عدداً صحيحاً بين 1 و 100.',
        'reason_invalid' => 'السبب يجب أن يكون نصاً لا يزيد عن 255 حرفاً.',
    ],

];

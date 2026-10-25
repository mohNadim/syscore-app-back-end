<?php

return [

    'created' => 'تم إنشاء الحجز بنجاح.',
    'cancelled' => 'تم إلغاء الحجز بنجاح.',
    'no_show' => 'تم تسجيل الحجز كعدم حضور.',
    'cannot_mark_no_show' => 'يمكن تسجيل عدم الحضور فقط للحجز المؤكد الذي بدأ موعده.',

    'validation' => [
        'date_invalid' => 'التاريخ يجب أن يكون بصيغة YYYY-MM-DD.',
        'to_after_from' => 'تاريخ النهاية لا يمكن أن يسبق تاريخ البداية.',
        'status_invalid' => 'الحالة يجب أن تكون إحدى القيم: pending_deposit، confirmed، completed، cancelled، expired، no_show.',
        'unit_id_invalid' => 'الوحدة يجب أن تكون معرّف وحدة قابلة للحجز موجودة.',
        'facility_id_invalid' => 'فلتر المنشأة يجب أن يكون عدداً صحيحاً.',
        'user_id_invalid' => 'المستخدم يجب أن يكون معرّف مستخدم موجود.',
        'phone_filter_invalid' => 'فلتر الهاتف يجب أن يكون نصاً لا يزيد عن 20 حرفاً.',
        'booking_number_invalid' => 'فلتر رقم الحجز يجب أن يكون نصاً لا يزيد عن 20 حرفاً.',
        'per_page_invalid' => 'حجم الصفحة يجب أن يكون عدداً صحيحاً بين 1 و 100.',
        'sport_id_invalid' => 'الرياضة مطلوبة ويجب أن تكون معرّف رياضة موجودة.',
        'starts_at_invalid' => 'بداية الحجز مطلوبة بصيغة YYYY-MM-DD HH:MM.',
        'duration_invalid' => 'المدة يجب أن تكون عدداً صحيحاً من الدقائق بين 1 و 600.',
        'guest_name_required' => 'اسم الضيف مطلوب عند عدم اختيار مستخدم.',
        'guest_name_invalid' => 'اسم الضيف يجب أن يكون بين 2 و 150 حرفاً.',
        'guest_phone_required' => 'هاتف الضيف مطلوب عند عدم اختيار مستخدم.',
        'guest_phone_invalid' => 'هاتف الضيف يجب أن يكون رقماً سورياً صحيحاً مثل +963912345678.',
        'payment_mode_invalid' => 'طريقة الدفع يجب أن تكون إحدى القيم: cash_full، deposit_then_cash، online_full.',
        'reason_required' => 'سبب الإلغاء مطلوب.',
        'reason_invalid' => 'سبب الإلغاء يجب أن يكون نصاً لا يزيد عن 255 حرفاً.',
    ],

];

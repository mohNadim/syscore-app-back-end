<?php

return [

    'created' => 'تم تسجيل الطفل.',
    'waitlisted' => 'المجموعة ممتلئة. تمت إضافة الطفل إلى قائمة الانتظار.',
    'ended' => 'تم إنهاء التسجيل.',
    'not_endable' => 'يمكن إنهاء التسجيل النشط فقط.',
    'subscription_cancelled' => 'تم إلغاء الاشتراك.',
    'subscription_not_cancellable' => 'لا يمكن إلغاء هذا الاشتراك بعد الآن.',
    'offered' => 'تم عرض مقعد على الطفل.',
    'removed' => 'تمت إزالة الطلب من قائمة الانتظار.',
    'no_free_seat' => 'لا يوجد مقعد شاغر في المجموعة لعرضه.',
    'not_offerable' => 'يمكن عرض مقعد على الطلبات المنتظِرة فقط.',
    'not_removable' => 'لا يمكن إزالة هذا الطلب من قائمة الانتظار بعد الآن.',

    'validation' => [
        'group_id_invalid' => 'المجموعة يجب أن تكون معرّف مجموعة أكاديمية موجودة.',
        'child_id_invalid' => 'الطفل يجب أن يكون معرّف طفل نشط موجود.',
        'user_id_invalid' => 'المستخدم يجب أن يكون معرّفاً رقمياً صحيحاً.',
        'status_invalid' => 'الحالة يجب أن تكون إحدى القيم: active, ended, cancelled.',
        'subscription_status_invalid' => 'الحالة يجب أن تكون إحدى القيم: pending_payment, active, expired, cancelled.',
        'expiring_within_days_invalid' => 'نافذة الانتهاء يجب أن تكون عدداً صحيحاً من الأيام بين 0 و365.',
        'override_capacity_invalid' => 'تجاوز السعة يجب أن يكون true أو false.',
        'reason_invalid' => 'السبب يجب أن يكون نصاً لا يزيد عن 255 حرفاً.',
        'per_page_invalid' => 'حجم الصفحة يجب أن يكون عدداً صحيحاً بين 1 و100.',
    ],

];

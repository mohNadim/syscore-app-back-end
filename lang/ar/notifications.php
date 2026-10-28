<?php

return [

    'read' => 'تم تعليم الإشعار كمقروء.',
    'read_all' => 'تم تعليم كل الإشعارات كمقروءة.',

    'events' => [
        'booking_confirmed' => [
            'title' => 'تم تأكيد حجزك',
            'body' => 'تم تأكيد حجزك رقم :booking_number في :unit بتاريخ :time.',
        ],
        'payment_succeeded' => [
            'title' => 'تمت عملية الدفع',
            'body' => 'تم استلام دفعتك بمبلغ :amount ل.س بنجاح.',
        ],
        'booking_reminder' => [
            'title' => 'تذكير بحجزك',
            'body' => 'يبدأ حجزك في :unit قريبًا (:time).',
        ],
        'child_absent' => [
            'title' => 'غياب الطفل',
            'body' => 'تم تسجيل غياب :child عن حصة :time.',
        ],
        'subscription_expiring' => [
            'title' => 'اشتراك على وشك الانتهاء',
            'body' => 'ينتهي اشتراك :child في مجموعة :group في :date.',
        ],
        'subscription_expired' => [
            'title' => 'انتهى الاشتراك',
            'body' => 'انتهى اشتراك :child في مجموعة :group. جدّد الاشتراك للمتابعة.',
        ],
        'session_cancelled' => [
            'title' => 'إلغاء حصة',
            'body' => 'تم إلغاء حصة :child في مجموعة :group المقررة في :time.',
        ],
        'session_updated' => [
            'title' => 'تعديل حصة',
            'body' => 'تم تعديل حصة :child في مجموعة :group، الموعد الجديد :time في :unit.',
        ],
        'discount_created' => [
            'title' => 'خصم جديد',
            'body' => 'يوجد خصم جديد متاح: :name.',
        ],
    ],

    'validation' => [
        'unread_invalid' => 'قيمة فلتر غير المقروء يجب أن تكون صحيحة أو خاطئة.',
        'per_page_invalid' => 'يجب أن يكون حجم الصفحة عددًا صحيحًا بين 1 و100.',
    ],

];

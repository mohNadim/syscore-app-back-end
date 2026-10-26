<?php

return [

    'renewal_requested' => 'تم طلب التجديد. ادفع الاشتراك الجديد للحفاظ على مقعدك.',
    'cancelled' => 'تم إلغاء الاشتراك.',
    'cancellation_scheduled' => 'تبقى الفترة المدفوعة سارية وسينتهي التسجيل في :date.',
    'not_renewable' => 'لا يمكن تجديد هذا الاشتراك.',
    'cancellation_scheduled_conflict' => 'تم جدولة إنهاء هذا التسجيل بالفعل، لذلك لا يمكن تجديده.',
    'renewal_pending' => 'يوجد طلب تجديد بانتظار الدفع بالفعل.',
    'already_cancelled' => 'تم جدولة إلغاء هذا التسجيل بالفعل.',
    'not_cancellable' => 'لا يمكن إلغاء هذا الاشتراك بعد الآن.',

    'validation' => [
        'child_id_invalid' => 'الطفل يجب أن يكون معرّفاً رقمياً صحيحاً.',
        'status_invalid' => 'الحالة يجب أن تكون إحدى القيم: pending_payment, active, expired, cancelled.',
        'per_page_invalid' => 'حجم الصفحة يجب أن يكون عدداً صحيحاً بين 1 و100.',
    ],

];

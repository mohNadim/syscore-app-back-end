<?php

return [

    'enrolled' => 'تم تسجيل الطفل. ادفع الاشتراك الأول لتأكيد المقعد.',
    'waitlisted' => 'المجموعة ممتلئة. تمت إضافة الطفل إلى قائمة الانتظار.',
    'already_enrolled' => 'هذا الطفل مسجّل بالفعل في هذه المجموعة.',
    'already_waitlisted' => 'هذا الطفل موجود بالفعل في قائمة انتظار هذه المجموعة.',
    'not_eligible' => 'لا يمكن للطفل الانضمام إلى هذه المجموعة: :reason.',
    'waitlist_left' => 'تمت إزالة الطفل من قائمة الانتظار.',
    'waitlist_not_removable' => 'لا يمكن إزالة هذا الطلب من قائمة الانتظار بعد الآن.',
    'waitlist_offer_expired' => 'انتهت صلاحية هذا العرض من قائمة الانتظار.',
    'waitlist_offer_not_available' => 'لا يوجد عرض مفتوح لهذا الطلب في قائمة الانتظار.',
    'cancelled' => 'تم إلغاء التسجيل.',
    'cancellation_scheduled' => 'تبقى فترتك المدفوعة سارية وسينتهي التسجيل عند انقضائها.',
    'cancellation_undone' => 'تم التراجع عن الإلغاء المجدول. يستمر التسجيل.',
    'enrollment_not_cancellable' => 'لا يمكن إلغاء هذا التسجيل بعد الآن.',
    'enrollment_already_cancelling' => 'إلغاء هذا التسجيل مجدول بالفعل.',
    'enrollment_not_cancelling' => 'لا يوجد إلغاء مجدول لهذا التسجيل للتراجع عنه.',

    'reasons' => [
        'group_inactive' => 'المجموعة أو الأكاديمية غير نشطة',
        'child_inactive' => 'الطفل غير نشط',
        'age' => 'عمر الطفل خارج النطاق العمري للمجموعة',
        'gender' => 'المجموعة لا تقبل جنس الطفل',
    ],

    'validation' => [
        'payment_method_invalid' => 'طريقة الدفع يجب أن تكون sham_cash أو cash_at_reception.',
        'child_id_invalid' => 'الطفل يجب أن يكون معرّف طفل موجود.',
        'group_id_invalid' => 'المجموعة يجب أن تكون معرّف مجموعة أكاديمية موجودة.',
        'status_invalid' => 'الحالة يجب أن تكون إحدى القيم: active, ended, cancelled.',
    ],

];

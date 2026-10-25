<?php

return [

    'cash_recorded' => 'تم تسجيل الدفعة النقدية بنجاح.',
    'refunded' => 'تم تنفيذ الاسترداد بنجاح.',

    'validation' => [
        'amount_invalid' => 'يجب أن يكون المبلغ عددًا صحيحًا لا يقل عن 1.',
        'payer_name_invalid' => 'يجب أن يكون اسم الدافع نصًا لا يتجاوز 150 حرفًا.',
        'payer_phone_invalid' => 'يجب أن يكون هاتف الدافع نصًا لا يتجاوز 20 حرفًا.',
        'refund_amount_invalid' => 'مبلغ الاسترداد مطلوب ويجب أن يكون عددًا صحيحًا لا يقل عن 1.',
        'reason_invalid' => 'يجب أن يكون السبب نصًا لا يتجاوز 255 حرفًا.',
        'status_invalid' => 'يجب أن تكون الحالة إحدى: pending, succeeded, failed, refunded, partially_refunded.',
        'method_invalid' => 'يجب أن تكون الطريقة sham_cash أو cash.',
        'purpose_invalid' => 'يجب أن يكون الغرض أحد: booking_deposit, booking_full, booking_balance, subscription.',
        'user_id_invalid' => 'يجب أن يكون فلتر المستخدم عددًا صحيحًا.',
        'date_invalid' => 'يجب أن يكون التاريخ بصيغة YYYY-MM-DD.',
        'to_after_from' => 'لا يمكن أن يسبق تاريخ النهاية تاريخ البداية.',
        'q_invalid' => 'يجب ألا يتجاوز نص البحث 50 حرفًا.',
    ],

];

<?php

return [

    'initiated' => 'تم بدء الدفع. أكمله عبر شام كاش.',
    'not_payable' => 'لا يوجد مبلغ مستحق الدفع على هذا العنصر حاليًا.',
    'not_refundable' => 'لا يمكن استرداد هذه الدفعة.',
    'refund_exceeds' => 'يجب أن يكون مبلغ الاسترداد موجبًا وألا يتجاوز الرصيد القابل للاسترداد.',
    'amount_exceeds_remaining' => 'المبلغ يتجاوز الرصيد المتبقي من الحجز.',
    'amount_below_deposit' => 'لا يمكن أن يقل المبلغ عن العربون المطلوب.',
    'amount_must_match_price' => 'يجب أن يساوي المبلغ سعر الاشتراك.',
    'invalid_signature' => 'توقيع الإشعار غير صالح.',
    'number_generation_failed' => 'تعذر إنشاء رقم الإيصال. حاول مرة أخرى.',

    'validation' => [
        'target_required' => 'أرسل booking_id أو subscription_id.',
        'target_exclusive' => 'أرسل واحدًا فقط من booking_id وsubscription_id.',
        'booking_id_invalid' => 'يجب أن يكون الحجز رقم معرّف صحيحًا.',
        'subscription_id_invalid' => 'يجب أن يكون الاشتراك رقم معرّف صحيحًا.',
        'per_page_invalid' => 'يجب أن يكون حجم الصفحة عددًا صحيحًا بين 1 و100.',
        'reference_invalid' => 'المرجع مطلوب ويجب أن يكون نصًا لا يتجاوز 100 حرف.',
        'status_invalid' => 'يجب أن تكون الحالة succeeded أو failed.',
        'amount_invalid' => 'يجب أن يكون المبلغ عددًا صحيحًا صفرًا أو أكثر.',
        'transaction_id_invalid' => 'يجب أن يكون معرّف العملية نصًا لا يتجاوز 100 حرف.',
    ],

];

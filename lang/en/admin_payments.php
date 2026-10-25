<?php

return [

    'cash_recorded' => 'Cash payment recorded successfully.',
    'refunded' => 'Refund processed successfully.',

    'validation' => [
        'amount_invalid' => 'The amount must be a whole number of at least 1.',
        'payer_name_invalid' => 'The payer name must be text of at most 150 characters.',
        'payer_phone_invalid' => 'The payer phone must be text of at most 20 characters.',
        'refund_amount_invalid' => 'The refund amount is required and must be a whole number of at least 1.',
        'reason_invalid' => 'The reason must be text of at most 255 characters.',
        'status_invalid' => 'The status must be one of: pending, succeeded, failed, refunded, partially_refunded.',
        'method_invalid' => 'The method must be sham_cash or cash.',
        'purpose_invalid' => 'The purpose must be one of: booking_deposit, booking_full, booking_balance, subscription.',
        'user_id_invalid' => 'The user filter must be a whole number.',
        'date_invalid' => 'The date must use the YYYY-MM-DD format.',
        'to_after_from' => 'The end date cannot be before the start date.',
        'q_invalid' => 'The search text must be at most 50 characters.',
    ],

];

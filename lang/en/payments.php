<?php

return [

    'initiated' => 'The payment was started. Complete it with Sham Cash.',
    'not_payable' => 'Nothing is payable on this item right now.',
    'not_refundable' => 'This payment cannot be refunded.',
    'refund_exceeds' => 'The refund amount must be positive and not exceed the refundable balance.',
    'amount_exceeds_remaining' => 'The amount exceeds the remaining balance of the booking.',
    'amount_below_deposit' => 'The amount cannot be less than the required deposit.',
    'amount_must_match_price' => 'The amount must equal the subscription price.',
    'invalid_signature' => 'The webhook signature is invalid.',
    'number_generation_failed' => 'A receipt number could not be generated. Please try again.',

    'validation' => [
        'target_required' => 'Send either a booking_id or a subscription_id.',
        'target_exclusive' => 'Send only one of booking_id and subscription_id.',
        'booking_id_invalid' => 'The booking must be a whole number id.',
        'subscription_id_invalid' => 'The subscription must be a whole number id.',
        'per_page_invalid' => 'The page size must be a whole number between 1 and 100.',
        'reference_invalid' => 'The reference is required and must be text of at most 100 characters.',
        'status_invalid' => 'The status must be succeeded or failed.',
        'amount_invalid' => 'The amount must be a whole number of zero or more.',
        'transaction_id_invalid' => 'The transaction id must be text of at most 100 characters.',
    ],

];

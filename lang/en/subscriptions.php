<?php

return [

    'renewal_requested' => 'Renewal requested. Pay the new subscription to keep your seat.',
    'cancelled' => 'The subscription was cancelled.',
    'cancellation_scheduled' => 'Your paid period stays valid and the enrollment will end on :date.',
    'not_renewable' => 'This subscription cannot be renewed.',
    'cancellation_scheduled_conflict' => 'The end of this enrollment is already scheduled, so it cannot be renewed.',
    'renewal_pending' => 'A renewal is already waiting for payment.',
    'already_cancelled' => 'The cancellation of this enrollment is already scheduled.',
    'not_cancellable' => 'This subscription can no longer be cancelled.',

    'validation' => [
        'child_id_invalid' => 'The child must be a whole number id.',
        'status_invalid' => 'The status must be one of: pending_payment, active, expired, cancelled.',
        'per_page_invalid' => 'The page size must be a whole number between 1 and 100.',
    ],

];

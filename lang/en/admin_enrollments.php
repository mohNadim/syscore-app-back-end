<?php

return [

    'created' => 'The child was enrolled.',
    'waitlisted' => 'The group is full. The child was added to the waitlist.',
    'ended' => 'The enrollment was ended.',
    'not_endable' => 'Only an active enrollment can be ended.',
    'subscription_cancelled' => 'The subscription was cancelled.',
    'subscription_not_cancellable' => 'This subscription can no longer be cancelled.',
    'offered' => 'A seat was offered to the child.',
    'removed' => 'The entry was removed from the waitlist.',
    'no_free_seat' => 'The group has no free seat to offer.',
    'not_offerable' => 'Only a waiting entry can be offered a seat.',
    'not_removable' => 'This waitlist entry can no longer be removed.',

    'validation' => [
        'group_id_invalid' => 'The group must be the id of an existing academy group.',
        'child_id_invalid' => 'The child must be the id of an existing active child.',
        'user_id_invalid' => 'The user must be a whole number id.',
        'status_invalid' => 'The status must be one of: active, ended, cancelled.',
        'subscription_status_invalid' => 'The status must be one of: pending_payment, active, expired, cancelled.',
        'expiring_within_days_invalid' => 'The expiry window must be a whole number of days from 0 to 365.',
        'override_capacity_invalid' => 'The capacity override must be true or false.',
        'reason_invalid' => 'The reason must be text of at most 255 characters.',
        'per_page_invalid' => 'The page size must be a whole number between 1 and 100.',
    ],

];

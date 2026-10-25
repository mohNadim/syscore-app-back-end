<?php

return [

    'session_updated' => 'Session updated successfully.',
    'session_cancelled' => 'Session cancelled successfully.',
    'session_locked' => 'Only scheduled or changed sessions that have not started yet can be modified.',
    'slot_taken' => 'The new time is already reserved on one of the unit spaces.',
    'closed' => 'The facility or one of its spaces is closed during the new time.',

    'validation' => [
        'academy_id_invalid' => 'The academy filter must be a whole number.',
        'group_id_invalid' => 'The group filter must be a whole number.',
        'unit_id_invalid' => 'The unit must be an existing unit.',
        'coach_id_invalid' => 'The coach must be an existing coach.',
        'status_invalid' => 'The status must be one of: scheduled, changed, cancelled, done.',
        'date_invalid' => 'The date must use the YYYY-MM-DD format.',
        'to_after_from' => 'The end date cannot be before the start date.',
        'per_page_invalid' => 'The page size must be a whole number between 1 and 100.',
        'times_together' => 'The start and end times must be sent together.',
        'datetime_invalid' => 'The date and time must use the YYYY-MM-DD HH:MM format.',
        'ends_before_starts' => 'The end must be after the start.',
        'starts_in_past' => 'The new start must be in the future.',
        'unit_inactive' => 'The unit and its facility must be active.',
        'unit_sport_mismatch' => 'The unit does not support the sport of this academy.',
        'change_reason_required' => 'The reason for the change is required.',
        'change_reason_invalid' => 'The reason must be text of at most 255 characters.',
        'reason_required' => 'The cancellation reason is required.',
        'reason_invalid' => 'The cancellation reason must be text of at most 255 characters.',
    ],

];

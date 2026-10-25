<?php

return [

    'schedule_created' => 'Schedule created successfully.',
    'schedule_updated' => 'Schedule updated successfully.',
    'schedule_deleted' => 'Schedule deleted successfully.',
    'schedule_ended' => 'The schedule already has sessions, so it was ended as of yesterday instead of being deleted.',
    'schedule_conflict' => 'The schedule conflicts with existing reservations on :count date(s). Nothing was saved.',

    'validation' => [
        'unit_id_required' => 'The unit is required.',
        'unit_id_invalid' => 'The unit must be an existing unit.',
        'unit_inactive' => 'The unit and its facility must be active.',
        'unit_sport_mismatch' => 'The unit does not support the sport of this academy.',
        'weekday_required' => 'The weekday is required.',
        'weekday_invalid' => 'The weekday must be a whole number from 0 (Sunday) to 6 (Saturday).',
        'start_time_required' => 'The start time is required.',
        'end_time_required' => 'The end time is required.',
        'time_invalid' => 'The time must use the HH:MM format.',
        'end_before_start' => 'The end time must be after the start time.',
        'outside_operating_hours' => 'The time range must be inside the facility operating hours for that weekday.',
        'valid_from_required' => 'The start date is required.',
        'valid_from_past' => 'The start date cannot be in the past.',
        'date_invalid' => 'The date must use the YYYY-MM-DD format.',
        'valid_to_before_from' => 'The end date cannot be before the start date.',
    ],

];

<?php

return [

    'attendance_saved' => 'Attendance saved successfully.',
    'session_not_markable' => 'Attendance can only be marked for sessions that have started and are not cancelled.',
    'child_not_enrolled' => 'This child is not actively enrolled in the session group.',

    'validation' => [
        'date_required' => 'The date is required.',
        'date_invalid' => 'The date must use the YYYY-MM-DD format.',
        'group_id_invalid' => 'The group filter must be a whole number.',
        'records_required' => 'The attendance records are required.',
        'records_invalid' => 'The records must be a list of 1 to 200 entries.',
        'child_id_required' => 'The child is required for every record.',
        'child_id_invalid' => 'The child must be a whole number.',
        'child_id_duplicate' => 'The same child cannot appear twice.',
        'status_required' => 'The attendance status is required for every record.',
        'status_invalid' => 'The status must be present or absent.',
    ],

];

<?php

return [

    'created' => 'Child added successfully.',
    'updated' => 'Child updated successfully.',
    'deleted' => 'Child deleted successfully.',
    'archived' => 'Child removed from your list. Their academy history is kept.',
    'has_active_enrollment' => 'This child is enrolled in an academy group. End the enrollment before removing the child.',

    'validation' => [
        'full_name_required' => 'The child\'s full name is required.',
        'full_name_invalid' => 'The child\'s full name must be text.',
        'full_name_length' => 'The child\'s full name must be between 2 and 150 characters.',
        'birth_date_required' => 'The birth date is required.',
        'birth_date_format' => 'The birth date must be in YYYY-MM-DD format.',
        'birth_date_range' => 'The birth date must be in the past and within the last 30 years.',
        'gender_required' => 'The gender is required.',
        'gender_invalid' => 'The gender must be male or female.',
        'health_notes_invalid' => 'The health notes must be text of at most 1000 characters.',
    ],

];

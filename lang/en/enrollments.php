<?php

return [

    'enrolled' => 'The child was enrolled. Pay the first subscription to confirm the seat.',
    'waitlisted' => 'The group is full. The child was added to the waitlist.',
    'already_enrolled' => 'This child is already enrolled in this group.',
    'already_waitlisted' => 'This child is already on the waitlist of this group.',
    'not_eligible' => 'The child cannot join this group: :reason.',
    'waitlist_left' => 'The child was removed from the waitlist.',
    'waitlist_not_removable' => 'This waitlist entry can no longer be removed.',

    'reasons' => [
        'group_inactive' => 'the group or its academy is not active',
        'child_inactive' => 'the child is not active',
        'age' => 'the child age is outside the group age range',
        'gender' => 'the group does not accept the child gender',
    ],

    'validation' => [
        'child_id_invalid' => 'The child must be the id of an existing child.',
        'group_id_invalid' => 'The group must be the id of an existing academy group.',
        'status_invalid' => 'The status must be one of: active, ended, cancelled.',
    ],

];

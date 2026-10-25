<?php

return [

    'created' => 'Role created successfully.',
    'updated' => 'Role updated successfully.',
    'deleted' => 'Role deleted successfully.',
    'system_role' => 'System roles cannot be deleted.',
    'in_use' => 'This role is assigned to staff members. Reassign them before deleting it.',
    'cannot_remove_own_roles_permission' => 'You cannot remove the role management permission from your own role.',

    'validation' => [
        'name_ar_required' => 'The Arabic role name is required.',
        'name_ar_invalid' => 'The Arabic role name must be text of at most 100 characters.',
        'name_ar_taken' => 'A role with this Arabic name already exists.',
        'name_en_invalid' => 'The English role name must be text of at most 100 characters.',
        'permission_ids_required' => 'The permissions list is required.',
        'permission_ids_invalid' => 'The permissions must be a list of permission ids.',
        'permission_id_invalid' => 'Each permission must be a valid permission id without duplicates.',
    ],

];

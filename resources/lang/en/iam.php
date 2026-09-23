<?php

/**
 * Iam module field labels - single source of truth for validation error
 * messages across all 4 Admin\Iam\* sub-modules, per
 * .ai/rules/conventions.md section 13. Mirrors resources/lang/en/booking.php.
 */
return [

    'fields' => [
        'code' => 'Code',
        'name' => 'Name',
        'description' => 'Description',
        'is_active' => 'Active Status',
        'guard_name' => 'Guard Name',
        'module_code' => 'Module',
        'process_code' => 'Process',
        'permissions' => 'Permissions',
    ],

];

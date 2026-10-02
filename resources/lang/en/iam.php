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

    // Flash messages shown on admin screens (to-do W6): wording lives here, controllers call __('iam.flash.key').
    'flash' => [
        'personal_details_updated' => 'Your personal details were updated.',
        'profile_photo_removed' => 'Your profile photo was removed.',
        'profile_photo_updated' => 'Your profile photo was updated.',
        'display_name_updated' => 'Your display name was updated.',
        'module_created_successfully' => 'Module created successfully!',
        'module_updated_successfully' => 'Module updated successfully!',
        'password_changed_other_sessions_were_signed' => 'Your password was changed. Other sessions were signed out.',
        'password_expired' => 'Your password has expired. Please choose a new one to continue.',
        'permission_created_successfully' => 'Permission created successfully!',
        'permission_updated_successfully' => 'Permission updated successfully!',
        'process_created_successfully' => 'Process created successfully!',
        'process_updated_successfully' => 'Process updated successfully!',
        'role_created_successfully' => 'Role created successfully!',
        'role_updated_successfully' => 'Role updated successfully!',
    ],

    // Password rules from Settings (N4, DEC-095 #28)
    'validation' => [
        'password_recently_used' => 'Choose a password you have not used for your last :count password changes.',
    ],

];

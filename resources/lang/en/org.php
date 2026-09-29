<?php

/**
 * Org module field labels - single source of truth for validation error
 * messages (via each FormRequest's attributes() method) across all 12
 * Admin\Org\* sub-modules, per .ai/rules/conventions.md section 13's
 * "centralized label/validation registry, future-translation-ready"
 * requirement. Mirrors the pattern established in resources/lang/en/booking.php.
 *
 * Keyed by field name. Shared field names (code, name, description,
 * is_active, etc.) mean the same thing across every sub-module, so one
 * flat key covers all of them - no need to duplicate per sub-module.
 */
return [

    'fields' => [
        // Shared across most Org sub-modules
        'code' => 'Code',
        'name' => 'Name',
        'description' => 'Description',
        'is_active' => 'Active Status',

        // Branch
        'address' => 'Address',
        'branch_image' => 'Branch Image',
        'city' => 'City',
        'email' => 'Email',
        'is_head_office' => 'Head Office Flag',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'phone' => 'Phone',
        'pincode' => 'Pincode',

        // Department
        'department_image' => 'Department Image',

        // Designation
        'designation_image' => 'Designation Image',
        'is_top_mgmt' => 'Top Management Flag',
        'parent_desig_code' => 'Parent Designation',
        'rank' => 'Rank',

        // Division
        'dept_code' => 'Department',
        'division_image' => 'Division Image',

        // Employee
        'designation_id' => 'Designation',
        'employment_type' => 'Employment Type',
        'joining_date' => 'Joining Date',
        'person_id' => 'Person',
        'primary_branch_id' => 'Primary Branch',
        'primary_department_id' => 'Primary Department',
        'resignation_date' => 'Resignation Date',

        // Location
        'branch_code' => 'Branch',
        'is_lmmws' => 'LMM Workshop Flag',
        'is_mwh' => 'MWH Flag',
        'is_office_only' => 'Office Only Flag',
        'is_parts_location' => 'Parts Location Flag',
        'is_sales_location' => 'Sales Location Flag',
        'is_stock_location' => 'Stock Location Flag',
        'is_workshop' => 'Workshop Flag',
        'location_image' => 'Location Image',

        // Person
        'aadhaar_no' => 'Aadhaar Number',
        'display_name' => 'Display Name',
        'dob' => 'Date of Birth',
        'entity_type' => 'Entity Type',
        'first_name' => 'First Name',
        'gender' => 'Gender',
        'gst_no' => 'GST Number',
        'last_name' => 'Last Name',
        'marital_status' => 'Marital Status',
        'middle_name' => 'Middle Name',
        'mobile' => 'Mobile Number',
        'occupation' => 'Occupation',
        'pan_no' => 'PAN Number',
        'salutation' => 'Salutation',
        'spouse_name' => 'Spouse Name',
        'tan_no' => 'TAN Number',

        // PersonAddress
        'address_line_1' => 'Address Line 1',
        'address_line_2' => 'Address Line 2',
        'country' => 'Country',
        'is_primary' => 'Primary Flag',
        'state' => 'State',
        'type' => 'Address Type',

        // PersonBankingDetail
        'account_holder_name' => 'Account Holder Name',
        'account_number' => 'Account Number',
        'account_type' => 'Account Type',
        'bank_name' => 'Bank Name',
        'branch_name' => 'Bank Branch Name',
        'ifsc_code' => 'IFSC Code',
        'is_verified' => 'Verified Flag',
        'swift_code' => 'SWIFT Code',

        // PersonContact
        'contact_detail' => 'Contact Detail',
        'contact_type' => 'Contact Type',
        'data_type' => 'Data Type',
        'person_code' => 'Person Code',

        // User
        'added_permissions' => 'Added Permissions',
        'addon_branch_codes' => 'Additional Branches',
        'addon_dept_codes' => 'Additional Departments',
        'addon_div_codes' => 'Additional Divisions',
        'addon_loc_codes' => 'Additional Locations',
        'addon_segment_codes' => 'Additional Segments',
        'addon_sub_segment_codes' => 'Additional Sub-Segments',
        'change_reason' => 'Change Reason',
        'date_of_joining' => 'Date of Joining',
        'designation_code' => 'Designation',
        'effective_date' => 'Effective Date',
        'password' => 'Password',
        'primary_branch_code' => 'Primary Branch',
        'primary_dept_code' => 'Primary Department',
        'primary_div_code' => 'Primary Division',
        'primary_loc_code' => 'Primary Location',
        'primary_segment_code' => 'Primary Segment',
        'primary_sub_segment_code' => 'Primary Sub-Segment',
        'remarks' => 'Remarks',
        'removed_permissions' => 'Removed Permissions',
        'role_id' => 'Role',
        'user_type_code' => 'User Type',
        'username' => 'Username',
        'vertical_code' => 'Vertical',

        // Vertical
        'vertical_image' => 'Vertical Image',
    ],

    // Flash messages shown on admin screens (to-do W6): wording lives here, controllers call __('org.flash.key').
    'flash' => [
        'address_removed' => 'Address removed.',
        'address_saved' => 'Address saved.',
        'address_updated' => 'Address updated.',
        'banking_detail_removed' => 'Banking detail removed.',
        'banking_detail_saved' => 'Banking detail saved.',
        'banking_detail_updated' => 'Banking detail updated.',
        'branch_created_successfully' => 'Branch created successfully!',
        'branch_updated_successfully' => 'Branch updated successfully!',
        'contact_removed' => 'Contact removed.',
        'contact_saved' => 'Contact saved.',
        'contact_updated' => 'Contact updated.',
        'department_created_successfully' => 'Department created successfully!',
        'department_updated_successfully' => 'Department updated successfully!',
        'designation_created_successfully' => 'Designation created successfully!',
        'designation_updated_successfully' => 'Designation updated successfully!',
        'division_created_successfully' => 'Division created successfully!',
        'division_updated_successfully' => 'Division updated successfully!',
        'location_created_successfully' => 'Location created successfully!',
        'location_updated_successfully' => 'Location updated successfully!',
        'person_contact_created_successfully' => 'Person Contact created successfully!',
        'person_contact_updated_successfully' => 'Person Contact updated successfully!',
        'person_created_successfully_add_more_contacts' => 'Person created successfully! Add more contacts, addresses, or banking details below.',
        'person_updated_successfully' => 'Person updated successfully!',
        'primary_address_updated' => 'Primary address updated.',
        'primary_bank_account_updated' => 'Primary bank account updated.',
        'primary_contact_updated' => 'Primary contact updated.',
        'user_access_revoked_role_all_permission' => 'User access revoked — role and all permission overrides cleared.',
        'user_activated' => 'User activated.',
        'user_created_successfully' => 'User created successfully!',
        'user_suspended_their_role_preserved_use' => 'User suspended. Their role is preserved — use Activate to restore access.',
        'user_updated_successfully' => 'User updated successfully!',
        'vertical_created_successfully' => 'Vertical created successfully!',
        'vertical_updated_successfully' => 'Vertical updated successfully!',
    ],

];

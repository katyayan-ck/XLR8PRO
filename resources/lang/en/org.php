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

];

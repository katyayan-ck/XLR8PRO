<?php

/**
 * Vehicle module field labels - single source of truth for validation error
 * messages across all 6 Admin\Vehicle\* sub-modules, per
 * .ai/rules/conventions.md section 13. Mirrors resources/lang/en/booking.php.
 */
return [

    'fields' => [
        // Shared across most Vehicle sub-modules
        'code' => 'Code',
        'name' => 'Name',
        'description' => 'Description',
        'is_active' => 'Active Status',
        'segment_code' => 'Segment',
        'sub_segment_code' => 'Sub-Segment',
        'sub_segment_id' => 'Sub-Segment',
        'segment_id' => 'Segment',
        'model_code' => 'Model',
        'variant_code' => 'Variant',

        // Color
        'hex_code' => 'Hex Color Code',

        // Variant
        'body_make_id' => 'Body Make',
        'body_type_id' => 'Body Type',
        'cc_capacity' => 'CC Capacity',
        'csd_index' => 'CSD Index',
        'custom_name' => 'Custom Name',
        'display_name' => 'Display Name',
        'drivetrain' => 'Drivetrain',
        'fuel_type_id' => 'Fuel Type',
        'gvw' => 'GVW',
        'is_csd' => 'CSD Flag',
        'oem_name' => 'OEM Name',
        'permit_id' => 'Permit Type',
        'seating_capacity' => 'Seating Capacity',
        'status_id' => 'Status',
        'taxi_price' => 'Taxi Price',
        'transmission' => 'Transmission',
        'wheels' => 'Number of Wheels',
    ],

];

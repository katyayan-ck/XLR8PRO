<?php

/**
 * Vehicle module field labels - single source of truth for validation error
 * messages across all 6 Admin\Vehicle\* sub-modules, per
 * .ai/rules/conventions.md section 13. Mirrors resources/lang/en/booking.php.
 */
return [

    'fields' => [
        // DEC-092 vehicle content
        'spec_category' => 'Specification category',
        'spec_item' => 'Specification',
        'unit' => 'Unit',
        'sort' => 'Order',
        'feature_group' => 'Feature group',
        'feature' => 'Feature',
        'value' => 'Value',
        'model' => 'Model',
        'variant' => 'Variant',
        'notes' => 'Notes',
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

    // Flash messages shown on admin screens (to-do W6): wording lives here, controllers call __('vehicle.flash.key').
    'flash' => [
        'import_failed' => 'Import failed: :message',
        'brand_updated_successfully' => 'Brand updated successfully!',
        'color_created_successfully' => 'Color created successfully!',
        'color_updated_successfully' => 'Color updated successfully!',
        'excel_file_empty' => 'Excel file is empty.',
        'import_completed' => 'Import Completed → :summary',
        'import_done_with_errors_br_check' => 'Import done with errors → :summary<br>Check laravel.log for failed rows.',
        'no_file_uploaded' => 'No file uploaded!',
        'only_excel_files_xlsx_xls_allowed' => 'Only Excel files (.xlsx, .xls) allowed',
        'segment_created_successfully' => 'Segment created successfully!',
        'segment_updated_successfully' => 'Segment updated successfully!',
        'sub_segment_created_successfully' => 'Sub Segment created successfully!',
        'sub_segment_updated_successfully' => 'Sub Segment updated successfully!',
        'variant_created_successfully' => 'Variant created successfully!',
        'variant_updated_successfully' => 'Variant updated successfully!',
        'vehicle_model_created_successfully' => 'Vehicle Model created successfully!',
        'vehicle_model_updated_successfully' => 'Vehicle Model updated successfully!',
    ],

];

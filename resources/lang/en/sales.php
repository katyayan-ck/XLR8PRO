<?php

/**
 * Sales module field labels - single source of truth for validation error
 * messages across Lead, LeadSource, and Campaign (Booking has its own
 * larger resources/lang/en/booking.php; Enquiry/Quotation not yet
 * migrated - see docs/changelog.md for follow-up scope), per
 * .ai/rules/conventions.md section 13.
 */
return [

    'fields' => [
        // Shared
        'name' => 'Name',
        'code' => 'Code',
        'description' => 'Description',
        'is_active' => 'Active Status',
        'mobile' => 'Mobile Number',
        'email' => 'Email',
        'segment_code' => 'Segment',
        'model_code' => 'Model',
        'branch_code' => 'Branch',
        'location_code' => 'Location',

        // Lead
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'occupation' => 'Occupation',
        'source_code' => 'Lead Source',
        'variant_code' => 'Variant',
        'color_code' => 'Color',
        'expected_delivery_date' => 'Expected Delivery Date',
        'priority' => 'Priority',
        'status' => 'Status',
        'notes' => 'Notes',
        'referral_details' => 'Referral Details',

        // Campaign
        'activity_code' => 'Activity',
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
    ],

    // Flash messages shown on admin screens (to-do W6): wording lives here, controllers call __('sales.flash.key').
    'flash' => [
        'error_saving_quotation' => 'Error saving quotation: :message',
        'error_updating_quotation' => 'Error updating quotation: :message',
        'campaign_created_successfully' => 'Campaign created successfully.',
        'campaign_updated_successfully' => 'Campaign updated successfully.',
        'choose_vehicle_its_colour_prices_come' => 'Choose the vehicle and its colour — prices come from the published price list.',
        'enquiry_created_successfully' => 'Enquiry created successfully.',
        'enquiry_updated_successfully' => 'Enquiry updated successfully.',
        'exchange_details_updated_successfully' => 'Exchange Details Updated successfully.',
        'file_uploaded_queued_processing_import' => 'File uploaded and queued for processing (Import #:import_log_id).',
        'finance_details_updated_successfully' => 'Finance Details Updated successfully.',
        'invalid_or_missing_file_only_excel' => 'Invalid or missing file! Only Excel files (.xlsx, .xls) allowed',
        'lead_created_successfully' => 'Lead created successfully!',
        'lead_source_created_successfully' => 'Lead Source created successfully!',
        'lead_source_updated_successfully' => 'Lead Source updated successfully!',
        'lead_updated_successfully' => 'Lead updated successfully!',
        'quotation_created_successfully' => 'Quotation created successfully.',
        'quotation_updated_successfully' => 'Quotation updated successfully.',
        'reference_enquiry_created_successfully' => 'Reference Enquiry created successfully.',
        'test_drive_scheduled_successfully' => 'Test Drive Scheduled successfully.',
        'test_drive_updated_successfully' => 'Test Drive Updated successfully.',
    ],

];

<?php

/**
 * Sales module field labels - single source of truth for validation error
 * messages across Lead, LeadSource, and Campaign (Booking has its own
 * larger resources/lang/en/booking.php; Enquiry/Quotation not yet
 * migrated - see docs/refactor/ai-changelogs for follow-up scope), per
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

];

<?php

/**
 * Booking module field labels - single source of truth for both Blade
 * form labels (via __('booking.fields.x')) and validation error messages
 * (via Validator::make()'s $customAttributes argument), per
 * .ai/rules/conventions.md section 13's "centralized label/validation
 * registry, future-translation-ready" requirement.
 *
 * Keyed by a stable, semantic field name - NOT by each form's raw input
 * name, since the same concept is submitted under different input names
 * across screens (e.g. store() reads 'bookingamount', update() reads
 * 'booking_amount' - a pre-existing inconsistency documented during the
 * Phase 3 refactor, left alone rather than renamed, since renaming form
 * inputs touches JS across every screen). Each screen's controller/view
 * maps its own input name to the matching semantic key below.
 */
return [

    'fields' => [
        'sale_type' => 'Sale Type',
        'customer_name' => 'Customer Name',
        'care_of_type' => 'Care Of',
        'care_of_name' => 'Care Of Name',
        'mobile' => 'Mobile Number',
        'alt_mobile' => 'Alternate Mobile Number',
        'gender' => 'Gender',
        'occupation' => 'Occupation',
        'pan_number' => 'PAN Number',
        'aadhaar_number' => 'Aadhaar Number',
        'gstin' => 'GSTIN',
        'customer_dob' => 'Customer Date of Birth',

        'branch' => 'Branch',
        'location' => 'Location',
        'location_other' => 'Other Location',

        'segment' => 'Segment',
        'model' => 'Model',
        'variant' => 'Variant',
        'color' => 'Color',
        'accessories' => 'Accessories',
        'apack_amount' => 'Accessories Pack Amount',
        'chassis_number' => 'Chassis Number',

        'buyer_type' => 'Buyer Type',
        'existing_make_1' => 'Existing Make 1',
        'existing_model_1' => 'Existing Model & Variant 1',
        'existing_make_2' => 'Existing Make 2',
        'existing_model_2' => 'Existing Model & Variant 2',
        'registration_number' => 'Registration Number',
        'manufacturing_year' => 'Manufacturing Year',
        'odometer_reading' => 'Odometer Reading',
        'expected_price' => 'Expected Price',
        'offered_price' => 'Offered Price',
        'exchange_bonus' => 'Exchange Bonus',

        'booking_mode' => 'Booking Mode',
        'online_reference_number' => 'Online Booking Reference Number',
        'booking_source' => 'Booking Source',
        'dsa' => 'DSA',
        'sales_consultant' => 'Sales Consultant',
        'delivery_type' => 'Delivery Type',
        'expected_delivery_date' => 'Expected Delivery Date',
        'finance_mode' => 'Finance Mode',
        'financier' => 'Financier',
        'loan_status' => 'Loan File Status',
        'make_sales_order' => 'Make Sales Order',
        'remarks' => 'Remarks',

        'referred_by' => 'Referred By',
        'referee_name' => 'Referred Customer Name',
        'referee_mobile' => 'Referred Customer Mobile Number',
        'referee_existing_model' => "Referred Customer's Existing Model",
        'referee_variant' => "Referred Customer's Variant",
        'referee_chassis_reg_no' => "Referred Customer's Chassis/Registration Number",

        'booking_amount' => 'Booking Amount',
        'booking_date' => 'Booking Date',
        'receipt_number' => 'Receipt/Voucher Number',
        'receipt_date' => 'Receipt/Voucher Date',
        'payment_mode' => 'Payment Mode',
        'dms_number' => 'DMS Number',
        'dms_otf' => 'DMS OTF',
        'chassis_number_short' => 'Chassis No.',
    ],

];

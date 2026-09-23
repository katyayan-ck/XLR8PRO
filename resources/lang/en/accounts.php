<?php

/**
 * Accounts module field labels - single source of truth for validation
 * error messages across JournalVoucher and Receipt, per
 * .ai/rules/conventions.md section 13. Mirrors resources/lang/en/booking.php.
 */
return [

    'fields' => [
        // Shared
        'location' => 'Location',
        'amount' => 'Amount',
        'customer_name' => 'Customer Name',
        'remarks' => 'Remarks',
        'on_account_of' => 'Account Head',

        // JournalVoucher
        'voucher_date' => 'Voucher Date',
        'jv_cat' => 'Voucher Category',
        'party_name' => 'Debit To (Party Name)',
        'used_model' => 'Used Car Model',
        'used_rgn_no' => 'Used Car Registration No.',
        'from_dept' => 'Transfer From Department',
        'to_dept' => 'Transfer To Department',
        'exist_receipt_no' => 'Existing Receipt No.',

        // Receipt
        'receipt_date' => 'Receipt Date',
        'payment_mode' => 'Payment Mode',
        'mobile' => 'Mobile Number',
        'xceler8_enq_no' => 'Enquiry Number',
        'vehicle_registration_no' => 'Vehicle Registration No.',
        'vehicle_chassis_no' => 'Vehicle Chassis No.',
        'invoice_no' => 'Invoice No.',
        'instrument_no' => 'Instrument No.',
        'bank_name' => 'Bank Name',
        'transaction_date' => 'Transaction Date',
    ],

];

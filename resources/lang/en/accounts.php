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

    // Flash messages shown on admin screens (to-do W6): wording lives here, controllers call __('accounts.flash.key').
    'flash' => [
        'error_creating_receipt' => 'Error creating receipt: :message',
        'error_creating_voucher' => 'Error creating voucher: :message',
        'error_updating_receipt' => 'Error updating receipt: :message',
        'error_updating_voucher' => 'Error updating voucher: :message',
        'receipt_created_successfully' => 'Receipt :receipt_no created successfully.',
        'receipt_updated_successfully' => 'Receipt :type_number updated successfully.',
        'voucher_created_successfully' => 'Voucher :voucher_no created successfully.',
        'voucher_updated_successfully' => 'Voucher :type_number updated successfully.',
    ],

];

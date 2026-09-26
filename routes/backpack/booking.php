<?php

use App\Http\Controllers\Admin\Sales\Booking\BookingCrudController;
use App\Http\Controllers\Admin\Sales\Enquiry\EnquiryCrudController;
use App\Http\Controllers\Admin\Sales\Quotation\QuotationCrudController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () {

    // =========== BOOKING ========================
    // Core CRUD (previously via Route::crud('booking', ...), registered explicitly — see
    // .ai/rules/module-structure.md; Route::crud() can't produce a slash-separated URL alongside a
    // dot-separated route name from a single $name argument, confirmed during batch 30).
    //
    // BUG-091: the explicit re-registration below dropped the 'operation' route-meta key that
    // Route::crud() used to set automatically. Without it, Backpack never calls
    // setupListOperation()/setupCreateOperation()/setupUpdateOperation() on the controller, so it
    // falls back to fully-generic rendering (an empty edit form, no custom list columns — see
    // known-bugs-report.md). Restored here using the same ['uses'=>..,'as'=>..,'operation'=>..]
    // style already used throughout routes/backpack/core.php.
    Route::get('sales/booking', ['uses' => BookingCrudController::class.'@index', 'as' => 'sales.booking.index', 'operation' => 'list']);
    Route::post('sales/booking', ['uses' => BookingCrudController::class.'@store', 'as' => 'sales.booking.store', 'operation' => 'create']);
    Route::get('sales/booking/create', ['uses' => BookingCrudController::class.'@create', 'as' => 'sales.booking.create', 'operation' => 'create']);
    Route::get('sales/booking/{id}/edit', ['uses' => BookingCrudController::class.'@edit', 'as' => 'sales.booking.edit', 'operation' => 'update']);
    Route::put('sales/booking/{id}', ['uses' => BookingCrudController::class.'@update', 'as' => 'sales.booking.update', 'operation' => 'update']);
    Route::delete('sales/booking/{id}', ['uses' => BookingCrudController::class.'@destroy', 'as' => 'sales.booking.destroy', 'operation' => 'delete']);
    Route::post('sales/booking/search', ['uses' => BookingCrudController::class.'@search', 'as' => 'sales.booking.search', 'operation' => 'list']);
    Route::get('sales/booking/{id}/details', ['uses' => BookingCrudController::class.'@showDetailsRow', 'as' => 'sales.booking.details', 'operation' => 'list']);
    Route::get('sales/booking/{id}/show', ['uses' => BookingCrudController::class.'@show', 'as' => 'sales.booking.show', 'operation' => 'show']);
    Route::get('sales/booking/{id}/preview', [BookingCrudController::class, 'preview'])->name('sales.booking.preview');

    // BUG-050's confirmed-broken methods (no real implementation) — kept registered under the new
    // URL scheme, still broken, not fixed here.
    Route::get('sales/booking/errors', [BookingCrudController::class, 'erroneousEntries'])
        ->name('sales.booking.errors');
    Route::get('sales/booking/errors/data', [BookingCrudController::class, 'erroneousEntriesData'])
        ->name('sales.booking.errors.data');
    Route::get('sales/booking/delivered', [BookingCrudController::class, 'delivered'])
        ->name('sales.booking.delivered');
    Route::get('sales/booking/delivered/list', [BookingCrudController::class, 'deliveredList'])
        ->name('sales.booking.delivered.list');
    Route::get('sales/booking/delivered-view/{id}', [BookingCrudController::class, 'deliveredView'])
        ->name('sales.booking.delivered-view');
    Route::get('sales/booking/finance/retailed', [BookingCrudController::class, 'finRetailed'])
        ->name('sales.booking.finance.retailed');
    Route::get('sales/booking/invoiced/list', [BookingCrudController::class, 'invoicedList'])
        ->name('sales.booking.invoiced.list');
    Route::get('sales/booking/pending-invoices/list', [BookingCrudController::class, 'pendingInvoicesList'])
        ->name('sales.booking.pending-invoices.list');
    // sales.booking.order-verify removed: orderVerify() never existed and nothing links to it (DEC-020).
    Route::get('sales/booking/reports/stock/list', [BookingCrudController::class, 'stockList'])
        ->name('sales.booking.reports.stock.list');
    Route::get('sales/booking/reports/live-order/list', [BookingCrudController::class, 'liveOrderList'])
        ->name('sales.booking.reports.live-order.list');
    Route::get('sales/booking/reports/pending-actions/list', [BookingCrudController::class, 'pendingActionsList'])
        ->name('sales.booking.reports.pending-actions.list');
    Route::get('sales/booking/{id}/refunded-view', [BookingCrudController::class, 'refundedView'])
        ->name('sales.booking.refunded-view');
    Route::get('sales/booking/{id}/scrappage-view', [BookingCrudController::class, 'scrappageView'])
        ->name('sales.booking.scrappage-view');

    Route::get('sales/booking/erroneous-bookings', [BookingCrudController::class, 'erroneousBookings'])
        ->name('sales.booking.erroneous-bookings');
    Route::get('sales/booking/finance/erroneous', [BookingCrudController::class, 'erroneousFinance'])
        ->name('sales.booking.finance.erroneous');
    Route::get('sales/booking/insurance/erroneous', [BookingCrudController::class, 'erroneousInsurance'])
        ->name('sales.booking.insurance.erroneous');
    Route::get('sales/booking/rto/erroneous', [BookingCrudController::class, 'erroneousRTO'])
        ->name('sales.booking.rto.erroneous');

    Route::get('sales/booking/otf-form/{id}', [BookingCrudController::class, 'otfProcess'])
        ->name('sales.booking.otf-process');
    Route::get('sales/booking/otf-form', [BookingCrudController::class, 'liveNotInvoiced'])
        ->name('sales.booking.otf-form');
    Route::post('sales/booking/{id}/otf-save', [BookingCrudController::class, 'otfSave'])
        ->name('sales.booking.otf.save');
    Route::get('sales/booking/{id}/generate-votf', [BookingCrudController::class, 'generateVotfNumber'])
        ->name('sales.booking.generate-votf');
    Route::get('sales/booking/{id}/download-otf-pdf', [BookingCrudController::class, 'downloadOtfPdf'])
        ->name('sales.booking.download-otf-pdf');

    Route::get('sales/booking/get-do-amount', [BookingCrudController::class, 'getDOAmount'])
        ->name('sales.booking.get-do-amount');
    Route::get('sales/booking/get-ta-statement', [BookingCrudController::class, 'getTAStatement'])
        ->name('sales.booking.get-ta-statement');

    // ================= QUOTATION =================

    Route::get('sales/quotation', [QuotationCrudController::class, 'index'])->name('sales.quotation.index');

    // sales.quotation.pending removed: pendingQuotations() never existed; hidden until Track B (DEC-023).

    Route::get('sales/quotation/create', [QuotationCrudController::class, 'create'])->name('sales.quotation.create');

    Route::post('sales/quotation/store', [QuotationCrudController::class, 'store'])->name('sales.quotation.store');

    Route::get('sales/quotation/{id}/edit', [QuotationCrudController::class, 'edit'])->name('sales.quotation.edit');

    Route::put('sales/quotation/{id}', [QuotationCrudController::class, 'update'])->name('sales.quotation.update');

    Route::get('sales/quotation/{quotation_no}/preview', [QuotationCrudController::class, 'preview'])->name('sales.quotation.preview');

    Route::get('sales/quotation/{id}/history', [QuotationCrudController::class, 'history'])->name('sales.quotation.history');

    Route::get('sales/quotation/{id}/history/{version}/pdf', [QuotationCrudController::class, 'historyPdf'])->name('sales.quotation.history-pdf');

    // ================= ADD BOOKING AMOUNT / RECEIPT =================
    Route::get('sales/booking/{id}/add-amount', [BookingCrudController::class, 'addAmountForm'])
        ->name('sales.booking.add-amount');
    Route::post('sales/booking/{id}/add-amount', [BookingCrudController::class, 'addAmount'])
        ->name('sales.booking.add-amount.store');
    Route::post('sales/booking/{id}/add-receipt', [BookingCrudController::class, 'addReceipt'])
        ->name('sales.booking.add-receipt.store');
    Route::post('sales/booking/request-refund/{id}', [BookingCrudController::class, 'requestRefund'])
        ->name('sales.booking.request-refund');
    Route::get('sales/booking/{id}/receipt/{receipt_id}/edit', [BookingCrudController::class, 'receiptEdit'])
        ->name('sales.booking.receipt.edit');
    Route::put('sales/booking/{bookingId}/receipt/{receiptId}', [BookingCrudController::class, 'receiptUpdate'])
        ->name('sales.booking.receipt.update');
    Route::get('sales/booking/pending-payment', [BookingCrudController::class, 'pendingPayment'])
        ->name('sales.booking.pending-payment');
    Route::get('sales/booking/check-receipt/{rn}', [BookingCrudController::class, 'CheckReceipt'])
        ->name('sales.booking.check-receipt');
    Route::get('sales/booking/{id}/check-field-payment', [BookingCrudController::class, 'checkFieldPayment'])
        ->name('sales.booking.check-field-payment');

    // ================= FOLLOWUP =================
    Route::post('sales/booking/followup', [BookingCrudController::class, 'storeFollowup'])
        ->name('sales.booking.followup.store');

    // ================= ORDER VERIFICATION =================
    Route::get('sales/booking/order-verification', [BookingCrudController::class, 'orderVerification'])
        ->name('sales.booking.order-verification');
    Route::get('sales/booking/order-update/{id}/{status}', [BookingCrudController::class, 'orderUpdate'])
        ->name('sales.booking.order-update')
        ->where(['id' => '[0-9]+', 'status' => '[0-5]']);
    Route::get('sales/booking/pending-order', [BookingCrudController::class, 'pendingorder'])
        ->name('sales.booking.pending-order');

    // ================= EDIT (generic booking status save) =================
    Route::post('sales/booking/{id}/statusave', [BookingCrudController::class, 'statusave'])
        ->name('sales.booking.statusave');
    Route::get('sales/booking/{id}/pending-edit', [BookingCrudController::class, 'pendingEdit'])
        ->name('sales.booking.pending-edit');
    Route::post('sales/booking/{id}/pending-update', [BookingCrudController::class, 'pendingUpdate'])
        ->name('sales.booking.pending-update');

    // ================= KYC =================
    Route::get('sales/booking/pending-kyc', [BookingCrudController::class, 'pendingKyc'])
        ->name('sales.booking.pending-kyc');
    Route::get('sales/booking/{id}/kyc-edit', [BookingCrudController::class, 'kycEdit'])
        ->name('sales.booking.kyc.edit');
    Route::put('sales/booking/{id}/kyc-update', [BookingCrudController::class, 'kycUpdate'])
        ->name('sales.booking.kyc.update');

    // ================= DMS =================
    Route::get('sales/booking/pending-dms', [BookingCrudController::class, 'pendingDms'])
        ->name('sales.booking.pending-dms');
    Route::get('sales/booking/{id}/dms-edit', [BookingCrudController::class, 'dmsedit'])
        ->name('sales.booking.dms-edit');
    Route::put('sales/booking/{id}/dms-update', [BookingCrudController::class, 'dmsupdate'])
        ->name('sales.booking.dms-update');

    // ================= VIEW: HOLD / CANCELLED / INVOICED =================
    Route::get('sales/booking/hold', [BookingCrudController::class, 'hold'])
        ->name('sales.booking.hold');
    Route::get('sales/booking/cancelled', [BookingCrudController::class, 'cancelled'])
        ->name('sales.booking.cancelled');
    Route::get('sales/booking/invoiced', [BookingCrudController::class, 'invoiced'])
        ->name('sales.booking.invoiced');
    Route::get('sales/booking/{id}/invoiced-show', [BookingCrudController::class, 'showInvoiced'])
        ->name('sales.booking.invoiced.show');

    // ================= PENDING: INSURANCE / RTO / DELIVERIES / REGISTRATION / DO =================
    Route::get('sales/booking/pending-insurance', [BookingCrudController::class, 'pendingInsurance'])
        ->name('sales.booking.pending-insurance');
    Route::get('sales/booking/pending-rto', [BookingCrudController::class, 'pendingRto'])
        ->name('sales.booking.pending-rto');
    Route::get('sales/booking/pending-deliveries', [BookingCrudController::class, 'pendingDeliveries'])
        ->name('sales.booking.pending-deliveries');
    Route::get('sales/booking/pending-registration', [BookingCrudController::class, 'pendingRegistration'])
        ->name('sales.booking.pending-registration');
    Route::get('sales/booking/pending-do', [BookingCrudController::class, 'pendingDO'])
        ->name('sales.booking.pending-do');
    Route::get('sales/booking/pending-invoices', [BookingCrudController::class, 'pendingInvoices'])
        ->name('sales.booking.pending-invoices');

    // ================= AJAX / HELPER LOOKUPS =================
    Route::get('sales/booking/branch-locations/{bid}', [BookingCrudController::class, 'getBranchLocation'])
        ->name('sales.booking.get-branch-location');
    Route::get('sales/booking/locations-by-branch/{branchCode?}', [BookingCrudController::class, 'getLocationsByBranch'])
        ->name('sales.booking.get-locations-by-branch');
    Route::get('sales/booking/locations/{state_id}', [BookingCrudController::class, 'getLocations'])
        ->name('sales.booking.get-locations');
    Route::get('sales/booking/locations-by-pincode/{pincode}', [BookingCrudController::class, 'getLocationsByPincode'])
        ->name('sales.booking.get-locations-by-pincode');
    Route::get('sales/booking/models/{segment_id}', [BookingCrudController::class, 'getModels'])
        ->name('sales.booking.get-models');
    Route::get('sales/booking/variants/{model}', [BookingCrudController::class, 'getVariants'])
        ->name('sales.booking.get-variants');
    Route::get('sales/booking/colors/{variant}', [BookingCrudController::class, 'getColors'])
        ->name('sales.booking.get-colors');
    Route::get('sales/booking/chassis-numbers/{modelCode}', [BookingCrudController::class, 'getChassisNumbers'])
        ->name('sales.booking.get-chassis-numbers');
    Route::get('sales/booking/accessories/{segment}/{model}/{variant}', [BookingCrudController::class, 'getAccessories'])
        ->name('sales.booking.get-accessories');
    Route::get('sales/booking/state-by-location/{location_id}', [BookingCrudController::class, 'getStateByLocation'])
        ->name('sales.booking.get-state-by-location');

    // ================= EXCHANGE / SCRAPPAGE =================
    Route::get('sales/booking/exchange', [BookingCrudController::class, 'Exchange'])
        ->name('sales.booking.exchange');
    Route::get('sales/booking/scrappage', [BookingCrudController::class, 'Scrappage'])
        ->name('sales.booking.scrappage');
    Route::get('sales/booking/exchange/not-interested', [BookingCrudController::class, 'exchnotInterested'])
        ->name('sales.booking.exchange.not-interested');
    Route::get('sales/booking/exchange/{id}/edit', [BookingCrudController::class, 'exchangeEdit'])
        ->name('sales.booking.exchange.edit');
    Route::put('sales/booking/exchange/{id}/update', [BookingCrudController::class, 'exchangeUpdate'])
        ->name('sales.booking.exchange.update');

    // ================= FINANCE =================
    Route::get('sales/booking/finance', [BookingCrudController::class, 'intInFinance'])
        ->name('sales.booking.finance');
    Route::get('sales/booking/finance/{id}/view', [BookingCrudController::class, 'financeView'])
        ->name('sales.booking.finance.view');
    Route::get('sales/booking/finance/not-interested', [BookingCrudController::class, 'finnotInterested'])
        ->name('sales.booking.finance.not-interested');
    Route::get('sales/booking/finance/retail', [BookingCrudController::class, 'finRetail'])
        ->name('sales.booking.finance.retail');
    Route::get('sales/booking/finance/payout', [BookingCrudController::class, 'finPayout'])
        ->name('sales.booking.finance.payout');
    Route::get('sales/booking/finance/payout/completed', [BookingCrudController::class, 'finPayoutCompleted'])
        ->name('sales.booking.finance.payout.completed');
    Route::get('sales/booking/finance/{id}/edit', [BookingCrudController::class, 'finEdit'])
        ->name('sales.booking.finance.edit');
    Route::put('sales/booking/finance/{id}/update', [BookingCrudController::class, 'finUpdate'])
        ->name('sales.booking.finance.update');
    Route::get('sales/booking/finance/{id}/retail-edit', [BookingCrudController::class, 'RetailEdit'])
        ->name('sales.booking.finance.retail-edit');
    Route::get('sales/booking/finance/{id}/payout-edit', [BookingCrudController::class, 'PayoutEdit'])
        ->name('sales.booking.finance.payout-edit');
    Route::put('sales/booking/finance/{id}/payout-update', [BookingCrudController::class, 'PayoutUpdate'])
        ->name('sales.booking.finance.payout-update');

    // ================= INSURANCE =================
    Route::get('sales/booking/insurance/{id}/edit', [BookingCrudController::class, 'insedit'])
        ->name('sales.booking.insurance.edit');
    Route::put('sales/booking/insurance/{id}', [BookingCrudController::class, 'insUpdate'])
        ->name('sales.booking.insurance.update');

    // ================= RTO =================
    Route::get('sales/booking/rto/{id}/edit', [BookingCrudController::class, 'rtoEdit'])
        ->name('sales.booking.rto.edit');
    Route::post('sales/booking/rto/{id}/update', [BookingCrudController::class, 'rtoUpdate'])
        ->name('sales.booking.rto.update');

    // ================= DELIVERY =================
    Route::get('sales/booking/{id}/delivery-edit', [BookingCrudController::class, 'PendDeliveryEdit'])
        ->name('sales.booking.delivery-photos.edit');
    Route::put('sales/booking/{id}/delivery-update', [BookingCrudController::class, 'PendDeliveryUpdate'])
        ->name('sales.booking.delivery-photos.update');

    // ================= DELIVERY ORDER (DO) =================
    Route::get('sales/booking/do/{id}/edit', [BookingCrudController::class, 'doEdit'])
        ->name('sales.booking.do.edit');
    Route::put('sales/booking/do/{id}', [BookingCrudController::class, 'doUpdate'])
        ->name('sales.booking.do.update');

    // ================= DEALER INVOICE =================
    Route::get('sales/booking/{id}/dealer-invoice', [BookingCrudController::class, 'dealerInvoice'])
        ->name('sales.booking.dealer-invoice');
    Route::put('sales/booking/{id}/dealer-invoice', [BookingCrudController::class, 'dealerInvoiceUpdate'])
        ->name('sales.booking.dealer-invoice.update');

    // ================= REFUND / REJECTED =================
    // Note: 'refundView' was previously reachable via 2 different URIs (both registered, pointing
    // at the identical method) — consolidated to 1 canonical route.
    Route::get('sales/booking/refund/requested', [BookingCrudController::class, 'refundRequested'])
        ->name('sales.booking.refund.requested');
    Route::get('sales/booking/{id}/refund-view', [BookingCrudController::class, 'refundView'])
        ->name('sales.booking.refund-view');
    Route::put('sales/booking/{id}/refund-update', [BookingCrudController::class, 'refundUpdate'])
        ->name('sales.booking.refund-update');
    Route::post('sales/booking/refund/{id}/edit', [BookingCrudController::class, 'editRefund'])
        ->name('sales.booking.refund.edit');
    Route::get('sales/booking/refunded', [BookingCrudController::class, 'refunded'])
        ->name('sales.booking.refunded');
    Route::put('sales/booking/{id}/refunded-update', [BookingCrudController::class, 'refundedUpdate'])
        ->name('sales.booking.refunded-update');
    Route::get('sales/booking/rejected', [BookingCrudController::class, 'rejected'])
        ->name('sales.booking.rejected');
    Route::get('sales/booking/{id}/rejected-view', [BookingCrudController::class, 'rejectedView'])
        ->name('sales.booking.rejected-view');

    // ================= REPORTS =================
    Route::get('sales/booking/reports/consolidated-booking', [BookingCrudController::class, 'consolidatedBookingReport'])
        ->name('sales.booking.reports.consolidated-booking');
    Route::get('sales/booking/reports/branch-booking', [BookingCrudController::class, 'branchBookingReport'])
        ->name('sales.booking.reports.branch-booking');
    Route::get('sales/booking/reports/stock', [BookingCrudController::class, 'stockReport'])
        ->name('sales.booking.reports.stock');
    Route::get('sales/booking/reports/live-order', [BookingCrudController::class, 'liveOrderReport'])
        ->name('sales.booking.reports.live-order');
    Route::get('sales/booking/reports/pending-actions', [BookingCrudController::class, 'pendingActionsReport'])
        ->name('sales.booking.reports.pending-actions');

    // ================= ACCOUNTS - ISSUE RECEIPT =================
    // (moved to routes/backpack/core.php — see BUG-036-pattern note there)

    // ================= SPECIAL DISCOUNT =================

    Route::get('special-discount', function () {
        return view('admin.sales-cashier.special-discount-list');
    })->name('special-discount.index');

    Route::get('special-discount/create', function () {
        return view('admin.sales-cashier.special-discount-create');
    })->name('special-discount.create');

    // ================= RTO CHARGES =================

    Route::get('rto-charges', function () {
        return view('admin.sales-cashier.rto-charges-list');
    })->name('rto-charges.index');

    Route::get('rto-charges/create', function () {
        return view('admin.sales-cashier.rto-charges-create');
    })->name('rto-charges.create');

    Route::get(
        'sales/enquiry/{id}/validate-quotation-vehicle',
        [EnquiryCrudController::class, 'validateQuotationVehicle']
    )->name('sales.enquiry.validate-quotation-vehicle');

});

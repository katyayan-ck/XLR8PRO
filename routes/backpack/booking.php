<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\BookingCrudController;
use App\Http\Controllers\Admin\QuotationCrudController;


Route::group([
    'prefix'     => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace'  => 'App\Http\Controllers\Admin',
], function () {

    // ================= CRUD =================
    Route::crud('user', 'UserCrudController');
    Route::crud('booking', 'BookingCrudController');

    Route::get('booking/errors', 'BookingCrudController@erroneousEntries')
        ->name('booking.errors');

    Route::get('booking/errors/data', 'BookingCrudController@erroneousEntriesData')
        ->name('booking.errors.data');

    Route::get(
        'booking/erroneous-bookings',
        'BookingCrudController@erroneousBookings'
    )->name('booking.erroneous-bookings');

    Route::get(
        'finance/erroneous',
        'BookingCrudController@erroneousFinance'
    )->name('finance.erroneous');

    Route::get(
        'insurance/erroneous',
        'BookingCrudController@erroneousInsurance'
    )->name('insurance.erroneous');

    Route::get(
        'rto/erroneous',
        'BookingCrudController@erroneousRTO'
    )->name('rto.erroneous');

    Route::get(
        'booking/otf-form/{id}',
        [BookingCrudController::class, 'otfProcess']
    )->name('booking.otf-process');

    Route::get(
        'booking/otf-form',
        [BookingCrudController::class, 'liveNotInvoiced']
    )->name('booking.otf-form');

    Route::get(
        'get-do-amount',
        [BookingCrudController::class, 'getDOAmount']
    )->name('booking.get-do-amount');

    // Route::get(
    //     'quotation-form',
    //     [BookingCrudController::class, 'quotationForm']
    // )->name('booking.quotation-form');


    // ================= QUOTATION =================


    Route::get(
        'quotation-form',
        [QuotationCrudController::class, 'index']
    )->name('quotation.index');

    Route::get(
        'quotation-form/create',
        [QuotationCrudController::class, 'create']
    )->name('quotation.create');

    Route::post(
        'quotation-form/store',
        [QuotationCrudController::class, 'store']
    )->name('quotation.store');

    Route::get(
        'quotation-form/{id}/edit',
        [QuotationCrudController::class, 'edit']
    )->name('quotation.edit');

    Route::put(
        'quotation-form/{id}',
        [QuotationCrudController::class, 'update']
    )->name('quotation.update');

    Route::get('quotation/{quotation_no}/preview', [QuotationCrudController::class, 'preview'])->name('quotation.preview');


    Route::get(
        'quotation-form/{id}/history',
        [QuotationCrudController::class, 'history']
    )->name('quotation.history');
    Route::get(
        'booking-process/{id}/preview',
        'BookingCrudController@preview'
    )->name('booking.preview');

    // ================= ADD BOOKING AMOUNT / RECEIPT =================
    Route::get('booking/{id}/add-amount', [
        'uses' => 'App\Http\Controllers\Admin\BookingCrudController@addAmountForm',
        'as'   => 'booking.add-amount'
    ]);

    Route::post('booking/{id}/add-amount', [
        'uses' => 'App\Http\Controllers\Admin\BookingCrudController@addAmount',
        'as'   => 'booking.add-amount.store'
    ]);
    Route::post('booking/{id}/add-receipt', [
        'uses' => 'App\Http\Controllers\Admin\BookingCrudController@addReceipt',
        'as'   => 'booking.add-receipt.store'
    ]);
    Route::post('booking/request-refund/{id}', 'BookingCrudController@requestRefund')
        ->name('request-refund');

    // ================= ORDER VERIFICATION =================
    Route::get('booking/order-verification', 'BookingCrudController@orderVerification')
        ->name('booking.order-verification');

    Route::post('booking/order-verify/{id}', 'BookingCrudController@orderVerify')
        ->name('booking.order-verify');

    Route::get('booking/order-update/{id}/{status}', [
        'uses'  => 'App\Http\Controllers\Admin\BookingCrudController@orderUpdate',
        'as'    => 'admin.booking.orderupdate',
    ])->where(['id' => '[0-9]+', 'status' => '[0-5]']);

    // ================= ORDERED VERIFICATION =================
    Route::get('booking/pending/sales-order', 'BookingCrudController@pendingorder')
        ->name('booking.pending-order');



    // ================= PENDING KYC =================
    Route::get(
        'booking/pending-kyc',
        'BookingCrudController@pendingKyc'
    )->name('booking.pending-kyc');



    // ================= KYC Edit & Update =================
    Route::get('booking/{id}/kyc-edit', 'BookingCrudController@kycEdit')
        ->name('booking.kyc.edit');
    Route::put('booking/{id}/kyc-update', 'BookingCrudController@kycUpdate')
        ->name('kyc.update');

    // ================= PENDING DMS =================
    Route::get('booking/pending-dms', 'BookingCrudController@pendingDms')
        ->name('booking.pending-dms');


    // DMS Edit & Update routes (custom, non-CRUD)
    Route::get('booking/{id}/dms-edit', 'BookingCrudController@dmsedit')->name('dms-edit');
    Route::put('booking/{id}/dms-update', 'BookingCrudController@dmsupdate')->name('dms.update');

    // ================= PENDING PAYMENT =================
    Route::get('booking/pending-payment', 'BookingCrudController@pendingPayment')
        ->name('booking.pending-payment');


    // On-Hold Bookings
    Route::get('booking/hold', 'BookingCrudController@hold')
        ->name('booking.hold');

    // Cancelled Bookings
    Route::get('booking/cancelled', 'BookingCrudController@cancelled')
        ->name('booking.cancelled');
    // ================= INVOICED BOOKINGS =================
    Route::get('booking/invoiced', 'BookingCrudController@invoiced')
        ->name('booking.invoiced');
    Route::get('booking/invoiced/list', 'BookingCrudController@invoicedList')
        ->name('booking.invoiced.list');


    // ================= PENDING INSURANCE =================
    Route::get('booking/pending-insurance', 'BookingCrudController@pendingInsurance')
        ->name('booking.pending-insurance');

    // ================= PENDING RTO =================
    Route::get('booking/pending-rto', 'BookingCrudController@pendingRto')
        ->name('booking.pending-rto');

    // ================= PENDING DELIVERIES =================
    Route::get('booking/pending-deliveries', 'BookingCrudController@pendingDeliveries')
        ->name('booking.pending-deliveries');

    // ================= PENDING REGISTRATION =================
    Route::get('booking/pending-registration', [App\Http\Controllers\Admin\BookingCrudController::class, 'pendingRegistration'])
        ->name('booking.pending-registration');

    // ================= PENDING DO (Delivery Order) =================
    Route::get('booking/pending-do', 'BookingCrudController@pendingDO')
        ->name('booking.pending-do');


    // AJAX / HELPER ROUTES

    Route::get('/branchlocations/{bid}', 'BookingCrudController@getBranchLocation')
        ->name('get.branch');


    Route::get(
        'get-locations/{branchCode?}',
        [App\Http\Controllers\Admin\BookingCrudController::class, 'getLocationsByBranch']
    )
        ->name('get-locations.by.branch');


    Route::get('get-locations/{state_id}', [App\Http\Controllers\Admin\BookingCrudController::class, 'getLocations'])
        ->name('get.locations');

    Route::get('get-locations-by-pincode/{pincode}', [App\Http\Controllers\Admin\BookingCrudController::class, 'getLocationsByPincode'])
        ->name('get.locations.by.pincode');

    Route::get('/get-models/{segment_id}', 'BookingCrudController@getModels')
        ->name('get.models');

    Route::get('/check-receipt/{rn}', 'BookingCrudController@CheckReceipt')
        ->name('check-receipt');

    Route::get('/get-variants/{model}', 'BookingCrudController@getVariants')
        ->name('get.variants');

    Route::get('/get-colors/{variant}', 'BookingCrudController@getColors')
        ->name('get.colors');

    Route::get('/get-chassis-numbers/{modelCode}', 'BookingCrudController@getChassisNumbers')
        ->name('get.chasis');

    Route::get('/get-accessories/{segment}/{model}/{variant}', 'BookingCrudController@getAccessories')
        ->name('get.accessories');


    Route::get('/get-state-by-location/{location_id}', 'BookingCrudController@getStateByLocation')
        ->name('get.state.by.location');
    Route::post('booking/followup', 'BookingCrudController@storeFollowup')
        ->name('booking.followup.store');



    Route::post('booking/{id}/statusave', 'BookingCrudController@statusave')
        ->name('statusave');

    Route::get('booking/refund/requested', 'BookingCrudController@refundRequested')
        ->name('booking.refund.requested')
        ->middleware('admin');

    Route::get('refund-view/{id}', 'BookingCrudController@refundView')
        ->name('refund.view')
        ->middleware('admin');

    Route::get('booking/refunded', 'BookingCrudController@refunded')
        ->name('booking.refunded')
        ->middleware('admin');

    Route::get('refunded-view/{id}', 'BookingCrudController@refundedView')
        ->name('refunded.view')
        ->middleware('admin');

    Route::get('booking/rejected', 'BookingCrudController@rejected')
        ->name('booking.rejected')
        ->middleware('admin');

    Route::get('rejected-view/{id}', 'BookingCrudController@rejectedView')
        ->name('rejected.view')
        ->middleware('admin');

    Route::get('booking/exchange', 'BookingCrudController@Exchange')
        ->name('booking.exchange')
        ->middleware('admin');


    Route::get('booking/scrappage', 'BookingCrudController@Scrappage')
        ->name('booking.scrappage')
        ->middleware('admin');

    Route::get('scrappage-view/{id}', 'BookingCrudController@scrappageView')
        ->name('scrappage.view')
        ->middleware('admin');

    // Not Interested Bookings List (ag-grid style)
    Route::get('/booking/exchange/not-interested', 'BookingCrudController@exchnotInterested')
        ->name('exchange.not-interested')
        ->middleware('admin');

    Route::get('booking/{id}/receipt/{receipt_id}/edit', 'BookingCrudController@receiptEdit')
        ->name('receipt.edit');
    Route::put('booking/{bookingId}/receipt/{receiptId}', 'BookingCrudController@receiptUpdate')
        ->name('receipt.update');


    Route::get('booking/finance', 'BookingCrudController@intInFinance')
        ->name('booking.finance')
        ->middleware('admin');

    Route::get('finance-view/{id}', 'BookingCrudController@financeView')
        ->name('finance.view')
        ->middleware('admin');

    Route::get('booking/finance/not-interested', 'BookingCrudController@finnotInterested')
        ->name('finance.not-interested')
        ->middleware('admin');



    Route::get('booking/finance/retail', 'BookingCrudController@finRetail')
        ->name('finance.retail')
        ->middleware('admin');

    Route::get('booking/finance/retailed', 'BookingCrudController@finRetailed')
        ->name('finance.retailed')
        ->middleware('admin');


    Route::get('finance/payout', 'BookingCrudController@finPayout')
        ->name('finance.payout')
        ->middleware('admin');

    Route::get('finance/payout/completed', 'BookingCrudController@finPayoutCompleted')
        ->name('finance.payout.completed')
        ->middleware('admin');
    Route::get('booking/{id}/invoiced-show', 'BookingCrudController@showInvoiced')
        ->name('booking.invoiced.show');
    Route::get('booking/{id}/pending-edit', 'BookingCrudController@pendingEdit')
        ->name('booking.pending-edit');
    Route::post('booking/{id}/pending-update', 'BookingCrudController@pendingUpdate')
        ->name('booking.pending-update');
    Route::get('reports/consolidated-booking', 'BookingCrudController@consolidatedBookingReport')
        ->name('reports.consolidated-booking')
        ->middleware('admin');

    Route::get('reports/branch-booking', 'BookingCrudController@branchBookingReport')
        ->name('reports.branch-booking')
        ->middleware('admin');

    Route::get('reports/stock', 'BookingCrudController@stockReport')
        ->name('reports.stock')
        ->middleware('admin');

    Route::get('reports/stock/list', 'BookingCrudController@stockList')
        ->name('reports.stock.list')
        ->middleware('admin');

    // ================= LIVE ORDER REPORT =================
    Route::get('reports/live-order', 'BookingCrudController@liveOrderReport')
        ->name('reports.live-order')
        ->middleware('admin');

    Route::get('reports/live-order/list', 'BookingCrudController@liveOrderList')
        ->name('reports.live-order.list')
        ->middleware('admin');

    Route::get('reports/pending-actions', 'BookingCrudController@pendingActionsReport')
        ->name('reports.pending-actions')
        ->middleware('admin');

    Route::get('reports/pending-actions/list', 'BookingCrudController@pendingActionsList')
        ->name('reports.pending-actions.list')
        ->middleware('admin');






    Route::get('booking/delivered', 'BookingCrudController@delivered')
        ->name('booking.delivered');

    Route::get('booking/delivered/list', 'BookingCrudController@deliveredList')
        ->name('booking.delivered.list');

    Route::get('booking/delivered-view/{id}', 'BookingCrudController@deliveredView')
        ->name('delivered-view');
    // ================= PENDING INVOICES =================
    Route::get('booking/pending-invoices', 'BookingCrudController@pendingInvoices')
        ->name('booking.pending-invoices');

    Route::get('booking/pending-invoices/list', 'BookingCrudController@pendingInvoicesList')
        ->name('booking.pending-invoices.list');

    Route::get('booking/{id}/receipt/{receipt_id}/edit', 'BookingCrudController@receiptEdit')
        ->name('receipt.edit');
    // ================= DEALER INVOICE OPERATIONS =================
    Route::get('booking/{id}/dealer-invoice', 'BookingCrudController@dealerInvoice')
        ->name('booking.dealer-invoice');

    Route::put('booking/{id}/dealer-invoice', 'BookingCrudController@dealerInvoiceUpdate')
        ->name('booking.dealer-invoice.update');
    Route::get('insurance/edit/{id}', 'BookingCrudController@insedit')->name('insurance.edit');
    Route::put('insurance/update/{id}', 'BookingCrudController@insUpdate')
        ->name('booking.insurance.update');

    Route::get('booking/rto/edit/{id}', 'BookingCrudController@rtoEdit')
        ->name('booking.rto.edit');

    Route::post('booking/rto/update/{id}', 'BookingCrudController@rtoUpdate')
        ->name('booking.rto.update');
    Route::get('booking/{id}/delivery-edit', 'BookingCrudController@PendDeliveryEdit')
        ->name('booking.delivery-photos.edit');

    Route::put('booking/{id}/delivery-update', 'BookingCrudController@PendDeliveryUpdate')
        ->name('booking.delivery-photos.update');

    Route::get('exchange/{id}/edit', 'BookingCrudController@exchangeEdit')
        ->name('exchange-edit')
        ->middleware('admin');

    Route::put('exchange/{id}/update', 'BookingCrudController@exchangeUpdate')
        ->name('exchange.update')
        ->middleware('admin');

    Route::get('finance/{id}/edit', 'BookingCrudController@finEdit')
        ->name('finance-edit')
        ->middleware('admin');
    Route::get('finance/{id}/retail-edit', 'BookingCrudController@RetailEdit')
        ->name('finance.retailedit')
        ->middleware('admin');

    Route::put('finance/{id}/update', 'BookingCrudController@finUpdate')
        ->name('finance.update')
        ->middleware('admin');

    Route::get('finance/{id}/payout-edit', 'BookingCrudController@PayoutEdit')
        ->name('finance.payoutedit')
        ->middleware('admin');
    Route::put('finance/{id}/payout-update', 'BookingCrudController@PayoutUpdate')
        ->name('payout.update')
        ->middleware('admin');

    Route::get('booking/{id}/refund-view', 'BookingCrudController@refundView')
        ->name('booking.refund-view');
    Route::put('booking/{id}/refund-update', 'BookingCrudController@refundUpdate')
        ->name('update-refund')
        ->middleware('admin');

    Route::post('/refund/edit/{id}', 'BookingCrudController@editRefund')
        ->name('editRefund');
    Route::put('booking/{id}/refunded-update', 'BookingCrudController@refundedUpdate')
        ->name('update-refunded')
        ->middleware('admin');
    Route::get('booking/{id}/check-field-payment', 'BookingCrudController@checkFieldPayment')
        ->name('booking.check-field-payment');

    Route::get('finance/do/edit/{id}', 'BookingCrudController@doEdit')
        ->name('finance.do.edit');

    Route::put('finance/do/update/{id}', 'BookingCrudController@doUpdate')
        ->name('finance.do.update');

    Route::get('booking/{id}/check-field-payment', 'BookingCrudController@checkFieldPayment')
        ->name('booking.check-field-payment');
    Route::post('booking/otf/{id}/save', 'BookingCrudController@otfSave')
        ->name('booking.otf.save');
});

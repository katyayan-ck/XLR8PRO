<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SubSegmentCrudController;
use App\Http\Controllers\Admin\VehicleModelCrudController;
use App\Http\Controllers\Admin\VehicleAccessoryCrudController;
use App\Http\Controllers\Admin\VariantCrudController;
use App\Http\Controllers\Admin\ColorCrudController;
use App\Http\Controllers\Admin\PermissionCrudController;
use App\Http\Controllers\Admin\LeadCrudController;
use App\Http\Controllers\Admin\LeadSourceCrudController;
use App\Http\Controllers\Admin\EnquiryCrudController;
use App\Http\Controllers\Admin\CampaignCrudController;

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () {

    Route::get('org-demo', [App\Http\Controllers\Admin\OrgDemoController::class, 'index'])->name('backpack.org.demo');

    Route::get('finance/import', [App\Http\Controllers\Admin\FinanceCrudController::class, 'import'])->name('finance.import');
    Route::get('insurance/import', [App\Http\Controllers\Admin\InsuranceCrudController::class, 'import'])->name('insurance.import');
    Route::get('rto/import', [App\Http\Controllers\Admin\RtoCrudController::class, 'import'])->name('rto.import');
    Route::post('segment/import', [App\Http\Controllers\Admin\SegmentCrudController::class, 'import'])->name('segment.import');

    Route::get('home', [DashboardController::class, 'index'])->name('backpack.dashboard.home');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('backpack.dashboard');

    // ==================== VEHICLE MODEL (Manual Routes) ====================
    Route::get('vehicle-model', [VehicleModelCrudController::class, 'index'])->name('vehicle-model.index');
    Route::get('vehicle-model/create', [VehicleModelCrudController::class, 'create'])->name('vehicle-model.create');
    Route::post('vehicle-model', [VehicleModelCrudController::class, 'store'])->name('vehicle-model.store');
    Route::get('vehicle-model/{id}/edit', [VehicleModelCrudController::class, 'edit'])->name('vehicle-model.edit');
    Route::put('vehicle-model/{id}', [VehicleModelCrudController::class, 'update'])->name('vehicle-model.update');
    Route::delete('vehicle-model/{id}', [VehicleModelCrudController::class, 'destroy'])->name('vehicle-model.destroy');
    Route::get('vehicle-model/sub-segments/{segmentCode}', [VehicleModelCrudController::class, 'getSubSegmentsBySegment']);

    Route::get('variant/subsegments', [VariantCrudController::class, 'getSubSegments']);
    Route::get('variant/models', [VariantCrudController::class, 'getModels']);

    Route::get('color/subsegments', [ColorCrudController::class, 'getSubSegments']);
    Route::get('color/models', [ColorCrudController::class, 'getModels']);
    Route::get('color/variants', [ColorCrudController::class, 'getVariants']);

    Route::get('permission/processes/{moduleCode}', [PermissionCrudController::class, 'getProcesses']);

    // ==================== STANDARD CRUD ROUTES ====================
    Route::crud('modules', 'ModulesCrudController');
    Route::crud('permission', 'PermissionCrudController');
    Route::crud('lead', 'LeadCrudController');
    Route::crud('role', 'RoleCrudController');
    Route::crud('process', 'ProcessCrudController');
    Route::crud('vehicle-accessory', VehicleAccessoryCrudController::class);
    Route::crud('system-settings', 'SystemSettingCrudController');
    Route::crud('branch', 'BranchCrudController');
    Route::crud('brand', 'BrandCrudController');
    Route::crud('color', 'ColorCrudController');
    Route::crud('department', 'DepartmentCrudController');
    Route::crud('designation', 'DesignationCrudController');
    Route::crud('division', 'DivisionCrudController');
    Route::crud('employee', 'EmployeeCrudController');
    Route::crud('keyvalue', 'KeyvalueCrudController');
    Route::crud('keyword-master', 'KeywordMasterCrudController');
    Route::crud('location', 'LocationCrudController');
    Route::crud('person-address', 'PersonAddressCrudController');
    Route::crud('person-banking-detail', 'PersonBankingDetailCrudController');
    Route::crud('person-contact', 'PersonContactCrudController');
    Route::crud('person', 'PersonCrudController');
    Route::crud('segment', 'SegmentCrudController');
    Route::crud('sub-segment', 'SubSegmentCrudController');
    Route::crud('variant', 'VariantCrudController');
    Route::crud('vertical', 'VerticalCrudController');
    Route::crud('user', 'UserCrudController');
    Route::crud('spare-request', 'SpareRequestCrudController');

    // Other custom routes
    Route::get('sub-segment/segments/{brandCode}', [SubSegmentCrudController::class, 'getSegmentsByBrand']);
    Route::get('sub-segment/sub-segments/{segmentCode}', [SubSegmentCrudController::class, 'getSubSegmentsBySegment']);

    // ==================== LEAD ====================
    Route::get('lead', [LeadCrudController::class, 'index'])->name('lead.index');
    Route::get('lead/create', [LeadCrudController::class, 'create'])->name('lead.create');
    Route::post('lead', [LeadCrudController::class, 'store'])->name('lead.store');
    Route::get('lead/{id}/edit', [LeadCrudController::class, 'edit'])->name('lead.edit');
    Route::put('lead/{id}', [LeadCrudController::class, 'update'])->name('lead.update');
    Route::delete('lead/{id}', [LeadCrudController::class, 'destroy'])->name('lead.destroy');
    Route::get('lead/models/{segmentCode}', [LeadCrudController::class, 'getModels']);
    Route::get('lead/variants/{modelCode}', [LeadCrudController::class, 'getVariants']);
    Route::get('lead/colors/{variantCode}', [LeadCrudController::class, 'getColors']);

    // =========== LEAD SOURCE ===================
    Route::get('lead-source/check-code', [LeadSourceCrudController::class, 'checkCode'])->name('lead-source.check-code');
    Route::crud('lead-source', 'LeadSourceCrudController');

    // =========== ENQUIRY ========================
    Route::crud('enquiry', 'EnquiryCrudController');

    // Add / Edit / Store / Export / Data
    Route::get('enquiries-list', [EnquiryCrudController::class, 'index'])->name('enquiry.index');
    Route::get('enquiries/add', [EnquiryCrudController::class, 'create'])->name('enquiry.create');
    Route::post('enquiries', [EnquiryCrudController::class, 'store']);
    Route::post('enquiries/data', [EnquiryCrudController::class, 'data']);
    Route::match(['get', 'post'], 'enquiry/data', [EnquiryCrudController::class, 'gridData'])->name('enquiry.data');
    Route::get('enquiries/export', [EnquiryCrudController::class, 'export']);
    Route::get('enquiry/export', [EnquiryCrudController::class, 'exportData'])->name('enquiry.export');

    // AJAX Lookups for Enquiry
    Route::get('enquiry/lead/{leadNo}', [EnquiryCrudController::class, 'getLead']);
    Route::get('enquiry/variants/{modelCode}', [EnquiryCrudController::class, 'getVariants']);
    Route::get('enquiry/colors/{variantCode}', [EnquiryCrudController::class, 'getColors']);
    Route::get('enquiry/models/{segmentCode}', [EnquiryCrudController::class, 'getModels']);
    Route::get('enquiry/sources', [EnquiryCrudController::class, 'getSources']);
    Route::get('enquiry/sales-consultants', [EnquiryCrudController::class, 'getSalesConsultants']);
    Route::get('admin/master/{keyword}/{parent}', [EnquiryCrudController::class, 'getKeywordValues'])->name('admin.master.keyword-values');
    Route::get('enquiry/locations/{branchCode}', [EnquiryCrudController::class, 'getLocations'])->name('enquiry.locations');
    Route::get('enquiry/reference-users', [EnquiryCrudController::class, 'getReferenceUsers'])->name('enquiry.reference-users');
    Route::get('enquiry/check-duplicate', [EnquiryCrudController::class, 'checkDuplicateEnquiry'])->name('enquiry.check-duplicate');
    Route::get('enquiry/location-by-pincode', [EnquiryCrudController::class, 'locationByPincode'])->name('enquiry.location-by-pincode');
    Route::get('accounts/receipt/fetch-enquiry', [App\Http\Controllers\Admin\ReceiptCrudController::class, 'fetchEnquiryDetails'])
        ->name('accounts.receipt.fetch-enquiry');

    // Specialized Enquiry Listings
    Route::get('enquiries/xceler8', [EnquiryCrudController::class, 'xceler8List'])->name('enquiry.xceler8');
    Route::get('enquiries/hyperlocal', [EnquiryCrudController::class, 'hyperlocalList'])->name('enquiry.hyperlocal');
    Route::get('enquiries/reference', [EnquiryCrudController::class, 'referenceList']);
    Route::get('enquiries/virtual-number', [EnquiryCrudController::class, 'virtualNumberList']);
    Route::get('enquiries/whatsapp-campaign', [EnquiryCrudController::class, 'whatsappCampaignList']);
    Route::get('enquiries/assigned-long', [EnquiryCrudController::class, 'assignedLongList']);
    Route::get('enquiries/unassigned-long', [EnquiryCrudController::class, 'unassignedLongList']);
    Route::get('enquiries/assigned-quick', [EnquiryCrudController::class, 'assignedQuickList']);
    Route::get('enquiries/unassigned-quick', [EnquiryCrudController::class, 'unassignedQuickList']);
    Route::get('enquiries/pending', [EnquiryCrudController::class, 'pendingList'])->name('enquiry.pending');
    Route::get('enquiries/erroneous', [EnquiryCrudController::class, 'erroneousList'])->name('enquiry.erroneous');

    // Reference Forms
    Route::get('enquiries/reference/add', [EnquiryCrudController::class, 'createReference'])->name('enquiry.reference.create');
    Route::post('enquiries/reference/store', [EnquiryCrudController::class, 'storeReference'])->name('enquiry.reference.store');

    // Import / Status
    Route::post('enquiry/import', [EnquiryCrudController::class, 'importEnquiries'])->name('enquiry.import');
    Route::get('enquiry/import/status/{id}', [EnquiryCrudController::class, 'importStatus'])->name('enquiry.import.status');
    Route::get('enquiry/import/history', [EnquiryCrudController::class, 'importHistory'])->name('enquiry.import.history');

    // =========================================================
    // EXCHANGE & SCRAPPAGE ENQUIRY ROUTES (New)
    // =========================================================
    Route::get('exchange/enquiry/int-in-exchange', [EnquiryCrudController::class, 'exchangeEnquiryList']);
    Route::get('exchange/enquiry/int-in-scrappage', [EnquiryCrudController::class, 'scrappageEnquiryList']);
    Route::get('exchange/enquiry/not-interested', [EnquiryCrudController::class, 'exchangeNotInterestedList']);
    Route::get('exchange/enquiry/{id}/edit', [EnquiryCrudController::class, 'exchangeEnquiryEdit']);
    Route::post('enquiry/{id}/exchange-update', [EnquiryCrudController::class, 'exchangeEnquiryUpdate']);

    // =========================================================
    // FINANCE ENQUIRY ROUTES (NEW)
    // =========================================================
    Route::get('finance/enquiry/int-in-finance', [EnquiryCrudController::class, 'financeEnquiryList']);
    Route::get('finance/enquiry/not-interested', [EnquiryCrudController::class, 'financeNotInterestedList']);
    Route::get('finance/enquiry/{id}/edit', [EnquiryCrudController::class, 'financeEnquiryEdit']);
    Route::put('finance/enquiry/{id}/update', [EnquiryCrudController::class, 'financeEnquiryUpdate'])->name('enquiry.finance.update');


    // =========== CAMPAIGN ========================
    Route::crud('campaign', 'CampaignCrudController');
    Route::get('campaign', [CampaignCrudController::class, 'index'])->name('campaign.index');
    Route::get('campaign/create', [CampaignCrudController::class, 'create'])->name('campaign.create');
    Route::post('campaign', [CampaignCrudController::class, 'store'])->name('campaign.store');
    Route::get('campaign/{id}/edit', [CampaignCrudController::class, 'edit'])->name('campaign.edit');
    Route::put('campaign/{id}', [CampaignCrudController::class, 'update'])->name('campaign.update');
    Route::delete('campaign/{id}', [CampaignCrudController::class, 'destroy'])->name('campaign.destroy');
    Route::get('campaign/models/{segmentCode}', [CampaignCrudController::class, 'getModels']);
    Route::get('campaign/locations/{branchCode}', [CampaignCrudController::class, 'getLocations']);

    // =========== TEST DRIVE =====================
    //Route::crud('testdrive', 'TestDriveCrudController');
    // =========== HYPERLOCAL ENQUIRIES ===========
    Route::get('enquiries/hyperlocal', [EnquiryCrudController::class, 'hyperlocalList'])
        ->name('enquiry.hyperlocal');
    Route::get('enquiries/otf-bookings', [EnquiryCrudController::class, 'otfBookingsList'])
        ->name('enquiry.otf-bookings');
    Route::get('enquiries/otf-bookings/{id}/show', [EnquiryCrudController::class, 'showOtf'])->name('enquiry.otf.show');
    Route::get('accounts/receipt/{id}/show', [App\Http\Controllers\Admin\ReceiptCrudController::class, 'show'])->name('accounts.receipt.show');
    Route::get('accounts/receipt/{id}/edit', [App\Http\Controllers\Admin\ReceiptCrudController::class, 'edit'])->name('accounts.receipt.edit');
    Route::put('accounts/receipt/{id}', [App\Http\Controllers\Admin\ReceiptCrudController::class, 'update'])->name('accounts.receipt.update');
    Route::get('accounts/journal-voucher-list', [App\Http\Controllers\Admin\JournalVoucherCrudController::class, 'index'])->name('accounts.journal-voucher.index');
    Route::get('accounts/journal-voucher/create', [App\Http\Controllers\Admin\JournalVoucherCrudController::class, 'create'])->name('accounts.journal-voucher.create');
    Route::post('accounts/journal-voucher-list', [App\Http\Controllers\Admin\JournalVoucherCrudController::class, 'store'])->name('accounts.journal-voucher.store');
    Route::get('accounts/journal-voucher/{id}/edit', [App\Http\Controllers\Admin\JournalVoucherCrudController::class, 'edit'])->name('accounts.journal-voucher.edit');
    Route::put('accounts/journal-voucher/{id}', [App\Http\Controllers\Admin\JournalVoucherCrudController::class, 'update'])->name('accounts.journal-voucher.update');
    Route::get('accounts/journal-voucher/fetch-enquiry', [App\Http\Controllers\Admin\JournalVoucherCrudController::class, 'fetchEnquiryDetails'])->name('accounts.journal-voucher.fetch-enquiry');
}); // ← This should be the last line
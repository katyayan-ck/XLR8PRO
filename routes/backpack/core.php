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


    Route::get('finance/import', [App\Http\Controllers\Admin\FinanceCrudController::class, 'import'])
        ->name('finance.import');
    Route::get('insurance/import', [App\Http\Controllers\Admin\InsuranceCrudController::class, 'import'])
        ->name('insurance.import');
    Route::get('rto/import', [App\Http\Controllers\Admin\RtoCrudController::class, 'import'])
        ->name('rto.import');
    Route::post('brand/import', [App\Http\Controllers\Admin\BrandCrudController::class, 'import'])
        ->name('brand.import');
    Route::get('home', [DashboardController::class, 'index'])->name('backpack.dashboard.home');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('backpack.dashboard');

    // ==================== VEHICLE MODEL (Manual Routes) ====================
    Route::get('vehicle-model', [VehicleModelCrudController::class, 'index'])->name('vehicle-model.index');
    Route::get('vehicle-model/create', [VehicleModelCrudController::class, 'create'])->name('vehicle-model.create');
    Route::post('vehicle-model', [VehicleModelCrudController::class, 'store'])->name('vehicle-model.store');
    Route::get('vehicle-model/{id}/edit', [VehicleModelCrudController::class, 'edit'])->name('vehicle-model.edit');
    Route::put('vehicle-model/{id}', [VehicleModelCrudController::class, 'update'])->name('vehicle-model.update');
    Route::delete('vehicle-model/{id}', [VehicleModelCrudController::class, 'destroy'])->name('vehicle-model.destroy');

    Route::get(
        'vehicle-model/sub-segments/{segmentCode}',
        [VehicleModelCrudController::class, 'getSubSegmentsBySegment']
    );



    Route::get(
        'variant/subsegments',
        [VariantCrudController::class, 'getSubSegments']
    );

    Route::get(
        'variant/models',
        [VariantCrudController::class, 'getModels']
    );

    Route::get(
        'color/subsegments',
        [ColorCrudController::class, 'getSubSegments']
    );

    Route::get(
        'color/models',
        [ColorCrudController::class, 'getModels']
    );

    Route::get(
        'color/variants',
        [ColorCrudController::class, 'getVariants']
    );

    Route::get(
        'permission/processes/{moduleCode}',
        [PermissionCrudController::class, 'getProcesses']
    );

    Route::get(
        'lead/variants/{modelCode}',
        [LeadCrudController::class, 'getVariants']
    );
    // Other Routes

    Route::crud('modules', 'ModulesCrudController');
    Route::crud('permission', 'PermissionCrudController');
    Route::crud('lead', 'LeadCrudController');
    Route::crud('role', 'RoleCrudController');
    Route::get(
        'lead/variants/{modelCode}',
        [LeadCrudController::class, 'getVariants']
    );

    Route::get(
        'lead/colors/{variantCode}',
        [LeadCrudController::class, 'getColors']
    );
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

    Route::get(
        'lead',
        [LeadCrudController::class, 'index']
    )->name('lead.index');

    Route::get(
        'lead/create',
        [LeadCrudController::class, 'create']
    )->name('lead.create');

    Route::post(
        'lead',
        [LeadCrudController::class, 'store']
    )->name('lead.store');

    Route::get(
        'lead/{id}/edit',
        [LeadCrudController::class, 'edit']
    )->name('lead.edit');

    Route::put(
        'lead/{id}',
        [LeadCrudController::class, 'update']
    )->name('lead.update');

    Route::delete(
        'lead/{id}',
        [LeadCrudController::class, 'destroy']
    )->name('lead.destroy');

    Route::get(
        'lead/models/{segmentCode}',
        [LeadCrudController::class, 'getModels']
    );
    // =========== LEAD SOURCE ===================


    Route::get(
        'lead-source/check-code',
        [LeadSourceCrudController::class, 'checkCode']
    )->name('lead-source.check-code');


    Route::crud('lead-source', 'LeadSourceCrudController');

    // =========== ENQUIRY ========================
    Route::crud(
        'enquiry',
        'EnquiryCrudController'
    );

    Route::get(
        'enquiry/lead/{leadNo}',
        [EnquiryCrudController::class, 'getLead']
    );

    Route::get(
        'enquiry/variants/{modelCode}',
        [EnquiryCrudController::class, 'getVariants']
    );

    Route::get(
        'enquiry/colors/{variantCode}',
        [EnquiryCrudController::class, 'getColors']
    );

    Route::get(
        'enquiry/models/{segmentCode}',
        [EnquiryCrudController::class, 'getModels']
    );

    Route::get('enquiries/add', [App\Http\Controllers\Admin\EnquiryCrudController::class, 'create'])
        ->name('enquiry.create');

    Route::post('enquiries', [App\Http\Controllers\Admin\EnquiryCrudController::class, 'store']);

    Route::get('enquiries-list', [App\Http\Controllers\Admin\EnquiryCrudController::class, 'index'])
        ->name('enquiry.index');

    Route::get('enquiry/sources', [App\Http\Controllers\Admin\EnquiryCrudController::class, 'getSources']);

    Route::get('enquiry/sales-consultants', [App\Http\Controllers\Admin\EnquiryCrudController::class, 'getSalesConsultants']);

    Route::get('enquiries/reference', [App\Http\Controllers\Admin\EnquiryCrudController::class, 'referenceList']);

    Route::get('enquiries/virtual-number', [App\Http\Controllers\Admin\EnquiryCrudController::class, 'virtualNumberList']);

    Route::get('enquiries/whatsapp-campaign', [App\Http\Controllers\Admin\EnquiryCrudController::class, 'whatsappCampaignList']);

    Route::get('enquiries/assigned-long', [App\Http\Controllers\Admin\EnquiryCrudController::class, 'assignedLongList']);

    Route::get('enquiries/unassigned-long', [App\Http\Controllers\Admin\EnquiryCrudController::class, 'unassignedLongList']);

    Route::get('enquiries/assigned-quick', [App\Http\Controllers\Admin\EnquiryCrudController::class, 'assignedQuickList']);

    Route::get('enquiries/unassigned-quick', [App\Http\Controllers\Admin\EnquiryCrudController::class, 'unassignedQuickList']);
    Route::post('enquiries/data', [EnquiryCrudController::class, 'data']);
    Route::get('enquiries/export', [EnquiryCrudController::class, 'export']);
    Route::post('enquiry/import', [App\Http\Controllers\Admin\EnquiryCrudController::class, 'importEnquiries'])
        ->name('enquiry.import');
    Route::get('enquiry/import/status/{id}', [App\Http\Controllers\Admin\EnquiryCrudController::class, 'importStatus'])
        ->name('enquiry.import.status');


    Route::match(['get', 'post'], 'enquiry/data', [EnquiryCrudController::class, 'gridData'])->name('enquiry.data');
    Route::get('enquiry/export', [EnquiryCrudController::class, 'exportData'])->name('enquiry.export');
    Route::get('enquiry/import/history', [App\Http\Controllers\Admin\EnquiryCrudController::class, 'importHistory'])
        ->name('enquiry.import.history');

    Route::get('enquiry/variants/{model_code}', [App\Http\Controllers\Admin\EnquiryCrudController::class, 'getVariants']);
    Route::get('enquiry/colors/{variant_code}', [App\Http\Controllers\Admin\EnquiryCrudController::class, 'getColors']);
    Route::get(
        'enquiry/lead/{leadNo}',
        [EnquiryCrudController::class, 'getLead']
    );

    /*
    ! Routes for Enquiry
     */

    Route::get(
        'admin/master/{keyword}/{parent}',
        [EnquiryCrudController::class, 'getKeywordValues']
    )->name('admin.master.keyword-values');

    Route::get(
        'enquiry/locations/{branchCode}',
        [EnquiryCrudController::class, 'getLocations']
    )->name('enquiry.locations');

    // Campaign crud

    Route::crud(
        'campaign',
        'CampaignCrudController'
    );

    Route::get(
        'campaign',
        [CampaignCrudController::class, 'index']
    )->name('campaign.index');

    Route::get(
        'campaign/create',
        [CampaignCrudController::class, 'create']
    )->name('campaign.create');

    Route::post(
        'campaign',
        [CampaignCrudController::class, 'store']
    )->name('campaign.store');

    Route::get(
        'campaign/{id}/edit',
        [CampaignCrudController::class, 'edit']
    )->name('campaign.edit');

    Route::put(
        'campaign/{id}',
        [CampaignCrudController::class, 'update']
    )->name('campaign.update');

    Route::delete(
        'campaign/{id}',
        [CampaignCrudController::class, 'destroy']
    )->name('campaign.destroy');
    Route::get(
        'campaign/models/{segmentCode}',
        [CampaignCrudController::class, 'getModels']
    );

    Route::get(
        'campaign/locations/{branchCode}',
        [CampaignCrudController::class, 'getLocations']
    );

    Route::get('enquiry/reference-users', [EnquiryCrudController::class, 'getReferenceUsers'])
        ->name('enquiry.reference-users');

    Route::get('enquiry/check-duplicate', [EnquiryCrudController::class, 'checkDuplicateEnquiry'])
        ->name('enquiry.check-duplicate');

    Route::get(
        'enquiry/location-by-pincode',
        [EnquiryCrudController::class, 'locationByPincode']
    )->name('enquiry.location-by-pincode');
}); // ← This should be the last line

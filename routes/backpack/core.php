<?php

use App\Http\Controllers\Admin\Accounts\JournalVoucher\JournalVoucherCrudController;
use App\Http\Controllers\Admin\Accounts\Receipt\ReceiptCrudController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Finance\FinanceCrudController;
use App\Http\Controllers\Admin\Iam\Modules\ModulesCrudController;
use App\Http\Controllers\Admin\Iam\Permission\PermissionCrudController;
use App\Http\Controllers\Admin\Iam\Process\ProcessCrudController;
use App\Http\Controllers\Admin\Iam\Role\RoleCrudController;
use App\Http\Controllers\Admin\Insurance\InsuranceCrudController;
use App\Http\Controllers\Admin\Org\Branch\BranchCrudController;
use App\Http\Controllers\Admin\Org\Department\DepartmentCrudController;
use App\Http\Controllers\Admin\Org\Designation\DesignationCrudController;
use App\Http\Controllers\Admin\Org\Division\DivisionCrudController;
use App\Http\Controllers\Admin\Org\Employee\EmployeeCrudController;
use App\Http\Controllers\Admin\Org\Location\LocationCrudController;
use App\Http\Controllers\Admin\Org\Person\PersonCrudController;
use App\Http\Controllers\Admin\Org\PersonAddress\PersonAddressCrudController;
use App\Http\Controllers\Admin\Org\PersonBankingDetail\PersonBankingDetailCrudController;
use App\Http\Controllers\Admin\Org\PersonContact\PersonContactCrudController;
use App\Http\Controllers\Admin\Org\User\UserCrudController;
use App\Http\Controllers\Admin\Org\Vertical\VerticalCrudController;
use App\Http\Controllers\Admin\OrgDemoController;
use App\Http\Controllers\Admin\Rto\RtoCrudController;
use App\Http\Controllers\Admin\Sales\Campaign\CampaignCrudController;
use App\Http\Controllers\Admin\Sales\Enquiry\EnquiryCrudController;
use App\Http\Controllers\Admin\Sales\Lead\LeadCrudController;
use App\Http\Controllers\Admin\Sales\LeadSource\LeadSourceCrudController;
use App\Http\Controllers\Admin\Spares\SpareRequest\SpareRequestCrudController;
use App\Http\Controllers\Admin\Utils\KeyValue\KeyValueCrudController;
use App\Http\Controllers\Admin\Utils\KeywordMaster\KeywordMasterCrudController;
use App\Http\Controllers\Admin\Utils\SystemSetting\SystemSettingCrudController;
use App\Http\Controllers\Admin\Vehicle\Brand\BrandCrudController;
use App\Http\Controllers\Admin\Vehicle\Color\ColorCrudController;
use App\Http\Controllers\Admin\Vehicle\Model\VehicleModelCrudController;
use App\Http\Controllers\Admin\Vehicle\Segment\SegmentCrudController;
use App\Http\Controllers\Admin\Vehicle\SubSegment\SubSegmentCrudController;
use App\Http\Controllers\Admin\Vehicle\Variant\VariantCrudController;
use App\Http\Controllers\Admin\VehicleAccessoryCrudController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
    'namespace' => 'App\Http\Controllers\Admin',
], function () {

    Route::get('org-demo', [OrgDemoController::class, 'index'])->name('backpack.org.demo');

    Route::get('finance/import', [FinanceCrudController::class, 'import'])->name('finance.import');
    Route::get('insurance/import', [InsuranceCrudController::class, 'import'])->name('insurance.import');
    Route::get('rto/import', [RtoCrudController::class, 'import'])->name('rto.import');
    Route::post('segment/import', [SegmentCrudController::class, 'import'])->name('segment.import');

    Route::get('home', [DashboardController::class, 'index'])->name('backpack.dashboard.home');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('backpack.dashboard');

    // ==================== VEHICLE MODEL (Manual Routes, no Operation traits) ====================
    Route::get('vehicle/model', [VehicleModelCrudController::class, 'index'])->name('vehicle.model.index');
    Route::get('vehicle/model/create', [VehicleModelCrudController::class, 'create'])->name('vehicle.model.create');
    Route::post('vehicle/model', [VehicleModelCrudController::class, 'store'])->name('vehicle.model.store');
    Route::get('vehicle/model/{id}/edit', [VehicleModelCrudController::class, 'edit'])->name('vehicle.model.edit');
    Route::put('vehicle/model/{id}', [VehicleModelCrudController::class, 'update'])->name('vehicle.model.update');
    // 'destroy' is a dead route — no destroy() method exists, no DeleteOperation trait used. See
    // known-bugs-report.md BUG-012 (pre-existing, documented, not fixed here — kept registered
    // under the new URL to preserve exact prior behavior).
    // vehicle.model.destroy removed: no destroy() and no delete button (DEC-020).
    Route::get('vehicle/model/sub-segments/{segmentCode}', [VehicleModelCrudController::class, 'getSubSegmentsBySegment'])->name('vehicle.model.get-sub-segments');

    Route::get('vehicle/variant/subsegments', [VariantCrudController::class, 'getSubSegments'])->name('vehicle.variant.get-sub-segments');
    Route::get('vehicle/variant/models', [VariantCrudController::class, 'getModels'])->name('vehicle.variant.get-models');

    Route::get('vehicle/color/subsegments', [ColorCrudController::class, 'getSubSegments'])->name('vehicle.color.get-sub-segments');
    Route::get('vehicle/color/models', [ColorCrudController::class, 'getModels'])->name('vehicle.color.get-models');
    Route::get('vehicle/color/variants', [ColorCrudController::class, 'getVariants'])->name('vehicle.color.get-variants');

    Route::get('iam/permission/processes/{moduleCode}', [PermissionCrudController::class, 'getProcesses'])->name('iam.permission.get-processes');

    // ==================== VEHICLE: BRAND / COLOR / SEGMENT / SUB-SEGMENT / VARIANT ====================
    // Registered with the explicit 'operation' key throughout (not just where strictly needed) —
    // all 5 controllers rely on setupListOperation()/setupCreateOperation()/setupUpdateOperation()
    // hooks (in addition to redundant inline checks in the overridden action methods), and
    // search()/showDetailsRow() have NO inline override at all, relying solely on the hook. See
    // .ai/rules/module-structure.md §3 and known-bugs-report.md BUG-064.
    Route::get('vehicle/brand', ['uses' => BrandCrudController::class.'@index', 'as' => 'vehicle.brand.index', 'operation' => 'list']);
    Route::post('vehicle/brand', ['uses' => BrandCrudController::class.'@store', 'as' => 'vehicle.brand.store', 'operation' => 'create']);
    Route::get('vehicle/brand/create', ['uses' => BrandCrudController::class.'@create', 'as' => 'vehicle.brand.create', 'operation' => 'create']);
    Route::post('vehicle/brand/search', ['uses' => BrandCrudController::class.'@search', 'as' => 'vehicle.brand.search', 'operation' => 'list']);
    Route::delete('vehicle/brand/{id}', ['uses' => BrandCrudController::class.'@destroy', 'as' => 'vehicle.brand.destroy', 'operation' => 'delete']);
    Route::put('vehicle/brand/{id}', ['uses' => BrandCrudController::class.'@update', 'as' => 'vehicle.brand.update', 'operation' => 'update']);
    Route::get('vehicle/brand/{id}/details', ['uses' => BrandCrudController::class.'@showDetailsRow', 'as' => 'vehicle.brand.details', 'operation' => 'list']);
    Route::get('vehicle/brand/{id}/edit', ['uses' => BrandCrudController::class.'@edit', 'as' => 'vehicle.brand.edit', 'operation' => 'update']);

    Route::get('vehicle/color', ['uses' => ColorCrudController::class.'@index', 'as' => 'vehicle.color.index', 'operation' => 'list']);
    Route::post('vehicle/color', ['uses' => ColorCrudController::class.'@store', 'as' => 'vehicle.color.store', 'operation' => 'create']);
    Route::get('vehicle/color/create', ['uses' => ColorCrudController::class.'@create', 'as' => 'vehicle.color.create', 'operation' => 'create']);
    Route::post('vehicle/color/search', ['uses' => ColorCrudController::class.'@search', 'as' => 'vehicle.color.search', 'operation' => 'list']);
    Route::delete('vehicle/color/{id}', ['uses' => ColorCrudController::class.'@destroy', 'as' => 'vehicle.color.destroy', 'operation' => 'delete']);
    Route::put('vehicle/color/{id}', ['uses' => ColorCrudController::class.'@update', 'as' => 'vehicle.color.update', 'operation' => 'update']);
    Route::get('vehicle/color/{id}/details', ['uses' => ColorCrudController::class.'@showDetailsRow', 'as' => 'vehicle.color.details', 'operation' => 'list']);
    Route::get('vehicle/color/{id}/edit', ['uses' => ColorCrudController::class.'@edit', 'as' => 'vehicle.color.edit', 'operation' => 'update']);

    Route::get('vehicle/segment', ['uses' => SegmentCrudController::class.'@index', 'as' => 'vehicle.segment.index', 'operation' => 'list']);
    Route::post('vehicle/segment', ['uses' => SegmentCrudController::class.'@store', 'as' => 'vehicle.segment.store', 'operation' => 'create']);
    Route::get('vehicle/segment/create', ['uses' => SegmentCrudController::class.'@create', 'as' => 'vehicle.segment.create', 'operation' => 'create']);
    Route::post('vehicle/segment/import', ['uses' => SegmentCrudController::class.'@import', 'as' => 'vehicle.segment.import', 'operation' => 'create']);
    Route::post('vehicle/segment/search', ['uses' => SegmentCrudController::class.'@search', 'as' => 'vehicle.segment.search', 'operation' => 'list']);
    Route::delete('vehicle/segment/{id}', ['uses' => SegmentCrudController::class.'@destroy', 'as' => 'vehicle.segment.destroy', 'operation' => 'delete']);
    Route::put('vehicle/segment/{id}', ['uses' => SegmentCrudController::class.'@update', 'as' => 'vehicle.segment.update', 'operation' => 'update']);
    Route::get('vehicle/segment/{id}/details', ['uses' => SegmentCrudController::class.'@showDetailsRow', 'as' => 'vehicle.segment.details', 'operation' => 'list']);
    Route::get('vehicle/segment/{id}/edit', ['uses' => SegmentCrudController::class.'@edit', 'as' => 'vehicle.segment.edit', 'operation' => 'update']);

    Route::get('vehicle/sub-segment', ['uses' => SubSegmentCrudController::class.'@index', 'as' => 'vehicle.sub-segment.index', 'operation' => 'list']);
    Route::post('vehicle/sub-segment', ['uses' => SubSegmentCrudController::class.'@store', 'as' => 'vehicle.sub-segment.store', 'operation' => 'create']);
    Route::get('vehicle/sub-segment/create', ['uses' => SubSegmentCrudController::class.'@create', 'as' => 'vehicle.sub-segment.create', 'operation' => 'create']);
    Route::post('vehicle/sub-segment/search', ['uses' => SubSegmentCrudController::class.'@search', 'as' => 'vehicle.sub-segment.search', 'operation' => 'list']);
    Route::delete('vehicle/sub-segment/{id}', ['uses' => SubSegmentCrudController::class.'@destroy', 'as' => 'vehicle.sub-segment.destroy', 'operation' => 'delete']);
    Route::put('vehicle/sub-segment/{id}', ['uses' => SubSegmentCrudController::class.'@update', 'as' => 'vehicle.sub-segment.update', 'operation' => 'update']);
    Route::get('vehicle/sub-segment/{id}/details', ['uses' => SubSegmentCrudController::class.'@showDetailsRow', 'as' => 'vehicle.sub-segment.details', 'operation' => 'list']);
    Route::get('vehicle/sub-segment/{id}/edit', ['uses' => SubSegmentCrudController::class.'@edit', 'as' => 'vehicle.sub-segment.edit', 'operation' => 'update']);
    Route::get('vehicle/sub-segment/segments/{brandCode}', [SubSegmentCrudController::class, 'getSegmentsByBrand'])->name('vehicle.sub-segment.get-segments');
    Route::get('vehicle/sub-segment/sub-segments/{segmentCode}', [SubSegmentCrudController::class, 'getSubSegmentsBySegment'])->name('vehicle.sub-segment.get-sub-segments');

    Route::get('vehicle/variant', ['uses' => VariantCrudController::class.'@index', 'as' => 'vehicle.variant.index', 'operation' => 'list']);
    Route::post('vehicle/variant', ['uses' => VariantCrudController::class.'@store', 'as' => 'vehicle.variant.store', 'operation' => 'create']);
    Route::get('vehicle/variant/create', ['uses' => VariantCrudController::class.'@create', 'as' => 'vehicle.variant.create', 'operation' => 'create']);
    Route::post('vehicle/variant/search', ['uses' => VariantCrudController::class.'@search', 'as' => 'vehicle.variant.search', 'operation' => 'list']);
    Route::delete('vehicle/variant/{id}', ['uses' => VariantCrudController::class.'@destroy', 'as' => 'vehicle.variant.destroy', 'operation' => 'delete']);
    Route::put('vehicle/variant/{id}', ['uses' => VariantCrudController::class.'@update', 'as' => 'vehicle.variant.update', 'operation' => 'update']);
    Route::get('vehicle/variant/{id}/details', ['uses' => VariantCrudController::class.'@showDetailsRow', 'as' => 'vehicle.variant.details', 'operation' => 'list']);
    Route::get('vehicle/variant/{id}/edit', ['uses' => VariantCrudController::class.'@edit', 'as' => 'vehicle.variant.edit', 'operation' => 'update']);

    // ==================== STANDARD CRUD ROUTES ====================
    // ==================== IAM: MODULES / PERMISSION / PROCESS / ROLE ====================
    // Explicit registrations with the 'operation' key preserved throughout — all 4 controllers rely
    // on setupListOperation() etc. hooks (plus redundant inline checks in most action methods, but
    // NOT in search()/showDetailsRow(), which have no override at all). See BUG-064 /
    // .ai/rules/module-structure.md §3.
    Route::get('iam/module', ['uses' => ModulesCrudController::class.'@index', 'as' => 'iam.module.index', 'operation' => 'list']);
    Route::post('iam/module', ['uses' => ModulesCrudController::class.'@store', 'as' => 'iam.module.store', 'operation' => 'create']);
    Route::get('iam/module/create', ['uses' => ModulesCrudController::class.'@create', 'as' => 'iam.module.create', 'operation' => 'create']);
    Route::post('iam/module/search', ['uses' => ModulesCrudController::class.'@search', 'as' => 'iam.module.search', 'operation' => 'list']);
    Route::delete('iam/module/{id}', ['uses' => ModulesCrudController::class.'@destroy', 'as' => 'iam.module.destroy', 'operation' => 'delete']);
    Route::put('iam/module/{id}', ['uses' => ModulesCrudController::class.'@update', 'as' => 'iam.module.update', 'operation' => 'update']);
    Route::get('iam/module/{id}/details', ['uses' => ModulesCrudController::class.'@showDetailsRow', 'as' => 'iam.module.details', 'operation' => 'list']);
    Route::get('iam/module/{id}/edit', ['uses' => ModulesCrudController::class.'@edit', 'as' => 'iam.module.edit', 'operation' => 'update']);

    Route::get('iam/permission', ['uses' => PermissionCrudController::class.'@index', 'as' => 'iam.permission.index', 'operation' => 'list']);
    Route::post('iam/permission', ['uses' => PermissionCrudController::class.'@store', 'as' => 'iam.permission.store', 'operation' => 'create']);
    Route::get('iam/permission/create', ['uses' => PermissionCrudController::class.'@create', 'as' => 'iam.permission.create', 'operation' => 'create']);
    Route::post('iam/permission/search', ['uses' => PermissionCrudController::class.'@search', 'as' => 'iam.permission.search', 'operation' => 'list']);
    Route::delete('iam/permission/{id}', ['uses' => PermissionCrudController::class.'@destroy', 'as' => 'iam.permission.destroy', 'operation' => 'delete']);
    Route::put('iam/permission/{id}', ['uses' => PermissionCrudController::class.'@update', 'as' => 'iam.permission.update', 'operation' => 'update']);
    Route::get('iam/permission/{id}/details', ['uses' => PermissionCrudController::class.'@showDetailsRow', 'as' => 'iam.permission.details', 'operation' => 'list']);
    Route::get('iam/permission/{id}/edit', ['uses' => PermissionCrudController::class.'@edit', 'as' => 'iam.permission.edit', 'operation' => 'update']);

    Route::get('iam/process', ['uses' => ProcessCrudController::class.'@index', 'as' => 'iam.process.index', 'operation' => 'list']);
    Route::post('iam/process', ['uses' => ProcessCrudController::class.'@store', 'as' => 'iam.process.store', 'operation' => 'create']);
    Route::get('iam/process/create', ['uses' => ProcessCrudController::class.'@create', 'as' => 'iam.process.create', 'operation' => 'create']);
    Route::post('iam/process/search', ['uses' => ProcessCrudController::class.'@search', 'as' => 'iam.process.search', 'operation' => 'list']);
    Route::delete('iam/process/{id}', ['uses' => ProcessCrudController::class.'@destroy', 'as' => 'iam.process.destroy', 'operation' => 'delete']);
    Route::put('iam/process/{id}', ['uses' => ProcessCrudController::class.'@update', 'as' => 'iam.process.update', 'operation' => 'update']);
    Route::get('iam/process/{id}/details', ['uses' => ProcessCrudController::class.'@showDetailsRow', 'as' => 'iam.process.details', 'operation' => 'list']);
    Route::get('iam/process/{id}/edit', ['uses' => ProcessCrudController::class.'@edit', 'as' => 'iam.process.edit', 'operation' => 'update']);

    Route::get('iam/role', ['uses' => RoleCrudController::class.'@index', 'as' => 'iam.role.index', 'operation' => 'list']);
    Route::post('iam/role', ['uses' => RoleCrudController::class.'@store', 'as' => 'iam.role.store', 'operation' => 'create']);
    Route::get('iam/role/create', ['uses' => RoleCrudController::class.'@create', 'as' => 'iam.role.create', 'operation' => 'create']);
    Route::post('iam/role/search', ['uses' => RoleCrudController::class.'@search', 'as' => 'iam.role.search', 'operation' => 'list']);
    Route::delete('iam/role/{id}', ['uses' => RoleCrudController::class.'@destroy', 'as' => 'iam.role.destroy', 'operation' => 'delete']);
    Route::put('iam/role/{id}', ['uses' => RoleCrudController::class.'@update', 'as' => 'iam.role.update', 'operation' => 'update']);
    Route::get('iam/role/{id}/details', ['uses' => RoleCrudController::class.'@showDetailsRow', 'as' => 'iam.role.details', 'operation' => 'list']);
    Route::get('iam/role/{id}/edit', ['uses' => RoleCrudController::class.'@edit', 'as' => 'iam.role.edit', 'operation' => 'update']);
    Route::crud('vehicle-accessory', VehicleAccessoryCrudController::class);

    // ==================== ORG: Branch/Department/Designation/Division/Employee/Location/Person/PersonAddress/PersonBankingDetail/PersonContact/Vertical ====================
    foreach ([
        ['branch', 'org.branch', BranchCrudController::class],
        ['department', 'org.department', DepartmentCrudController::class],
        ['designation', 'org.designation', DesignationCrudController::class],
        ['division', 'org.division', DivisionCrudController::class],
        ['employee', 'org.employee', EmployeeCrudController::class],
        ['location', 'org.location', LocationCrudController::class],
        ['person', 'org.person', PersonCrudController::class],
        ['person-address', 'org.person-address', PersonAddressCrudController::class],
        ['person-banking-detail', 'org.person-banking-detail', PersonBankingDetailCrudController::class],
        ['person-contact', 'org.person-contact', PersonContactCrudController::class],
        ['vertical', 'org.vertical', VerticalCrudController::class],
    ] as [$slug, $name, $controller]) {
        $uri = 'org/'.$slug;
        Route::get($uri, ['uses' => $controller.'@index', 'as' => $name.'.index', 'operation' => 'list']);
        Route::post($uri, ['uses' => $controller.'@store', 'as' => $name.'.store', 'operation' => 'create']);
        Route::get($uri.'/create', ['uses' => $controller.'@create', 'as' => $name.'.create', 'operation' => 'create']);
        Route::post($uri.'/search', ['uses' => $controller.'@search', 'as' => $name.'.search', 'operation' => 'list']);
        Route::delete($uri.'/{id}', ['uses' => $controller.'@destroy', 'as' => $name.'.destroy', 'operation' => 'delete']);
        Route::put($uri.'/{id}', ['uses' => $controller.'@update', 'as' => $name.'.update', 'operation' => 'update']);
        Route::get($uri.'/{id}/details', ['uses' => $controller.'@showDetailsRow', 'as' => $name.'.details', 'operation' => 'list']);
        Route::get($uri.'/{id}/edit', ['uses' => $controller.'@edit', 'as' => $name.'.edit', 'operation' => 'update']);
    }

    Route::put('org/designation/{id}/permissions', ['uses' => DesignationCrudController::class.'@updatePermissions', 'as' => 'org.designation.permissions', 'operation' => 'update']);

    // ==================== ORG: Person — integrated contacts/addresses/banking sub-resources ====================
    foreach ([
        ['contacts', 'contact', 'Contact'],
        ['addresses', 'address', 'Address'],
        ['banking', 'banking', 'Banking'],
    ] as [$slug, $singular, $method]) {
        $uri = "org/person/{id}/{$slug}";
        Route::post($uri, ['uses' => PersonCrudController::class."@store{$method}", 'as' => "org.person.{$singular}.store", 'operation' => 'update']);
        Route::put("{$uri}/{{$singular}Id}", ['uses' => PersonCrudController::class."@update{$method}", 'as' => "org.person.{$singular}.update", 'operation' => 'update']);
        Route::delete("{$uri}/{{$singular}Id}", ['uses' => PersonCrudController::class."@destroy{$method}", 'as' => "org.person.{$singular}.destroy", 'operation' => 'update']);
        Route::post("{$uri}/{{$singular}Id}/primary", ['uses' => PersonCrudController::class."@primary{$method}", 'as' => "org.person.{$singular}.primary", 'operation' => 'update']);
    }

    // ==================== ORG: User ====================
    Route::get('org/user', ['uses' => UserCrudController::class.'@index', 'as' => 'org.user.index', 'operation' => 'list']);
    Route::post('org/user', ['uses' => UserCrudController::class.'@store', 'as' => 'org.user.store', 'operation' => 'create']);
    Route::get('org/user/create', ['uses' => UserCrudController::class.'@create', 'as' => 'org.user.create', 'operation' => 'create']);
    Route::get('org/user/search-persons', ['uses' => UserCrudController::class.'@searchPersons', 'as' => 'org.user.search-persons', 'operation' => 'create']);
    Route::post('org/user/search', ['uses' => UserCrudController::class.'@search', 'as' => 'org.user.search', 'operation' => 'list']);
    Route::delete('org/user/{id}', ['uses' => UserCrudController::class.'@destroy', 'as' => 'org.user.destroy', 'operation' => 'delete']);
    Route::put('org/user/{id}', ['uses' => UserCrudController::class.'@update', 'as' => 'org.user.update', 'operation' => 'update']);
    Route::get('org/user/{id}/show', ['uses' => UserCrudController::class.'@show', 'as' => 'org.user.show', 'operation' => 'list']);
    Route::get('org/user/{id}/details', ['uses' => UserCrudController::class.'@showDetailsRow', 'as' => 'org.user.details', 'operation' => 'list']);
    Route::get('org/user/{id}/edit', ['uses' => UserCrudController::class.'@edit', 'as' => 'org.user.edit', 'operation' => 'update']);
    Route::post('org/user/{id}/suspend', ['uses' => UserCrudController::class.'@suspend', 'as' => 'org.user.suspend', 'operation' => 'update']);
    Route::post('org/user/{id}/revoke', ['uses' => UserCrudController::class.'@revoke', 'as' => 'org.user.revoke', 'operation' => 'delete']);
    Route::post('org/user/{id}/activate', ['uses' => UserCrudController::class.'@activate', 'as' => 'org.user.activate', 'operation' => 'update']);

    // =========== UTILS: SYSTEM SETTINGS ==========
    // (previously via Route::crud('system-settings', ...); explicit for slash-URL/dot-name split)
    // NOTE: these carry an explicit 'operation' key — SystemSettingCrudController relies solely on
    // Backpack's setupListOperation()/setupCreateOperation()/setupUpdateOperation() hooks for its
    // permission checks (no redundant inline override in index()/create()/edit()), and those hooks
    // are dispatched based on \Route::getCurrentRoute()->action['operation']. Registering via plain
    // [Controller::class, 'method'] (as done for every other migrated controller in this rollout)
    // omits that key, which Route::crud()/setupXRoutes() always include — silently breaking the
    // hooks and leaving index()/create() completely unguarded. See known-bugs-report.md BUG-064.
    Route::get('utils/system-setting', ['uses' => SystemSettingCrudController::class.'@index', 'as' => 'utils.system-setting.index', 'operation' => 'list']);
    Route::post('utils/system-setting', ['uses' => SystemSettingCrudController::class.'@store', 'as' => 'utils.system-setting.store', 'operation' => 'create']);
    Route::get('utils/system-setting/create', ['uses' => SystemSettingCrudController::class.'@create', 'as' => 'utils.system-setting.create', 'operation' => 'create']);
    Route::get('utils/system-setting/{id}/edit', ['uses' => SystemSettingCrudController::class.'@edit', 'as' => 'utils.system-setting.edit', 'operation' => 'update']);
    Route::put('utils/system-setting/{id}', ['uses' => SystemSettingCrudController::class.'@update', 'as' => 'utils.system-setting.update', 'operation' => 'update']);
    Route::delete('utils/system-setting/{id}', [SystemSettingCrudController::class, 'destroy'])->name('utils.system-setting.destroy');
    Route::post('utils/system-setting/search', ['uses' => SystemSettingCrudController::class.'@search', 'as' => 'utils.system-setting.search', 'operation' => 'list']);
    Route::get('utils/system-setting/{id}/details', ['uses' => SystemSettingCrudController::class.'@showDetailsRow', 'as' => 'utils.system-setting.details', 'operation' => 'list']);
    Route::get('utils/system-setting/{id}/show', [SystemSettingCrudController::class, 'show'])->name('utils.system-setting.show');

    // =========== UTILS: KEY VALUE ==========
    Route::get('utils/key-value', [KeyValueCrudController::class, 'index'])->name('utils.key-value.index');
    Route::post('utils/key-value', [KeyValueCrudController::class, 'store'])->name('utils.key-value.store');
    Route::get('utils/key-value/create', [KeyValueCrudController::class, 'create'])->name('utils.key-value.create');
    Route::get('utils/key-value/{id}/edit', [KeyValueCrudController::class, 'edit'])->name('utils.key-value.edit');
    Route::put('utils/key-value/{id}', [KeyValueCrudController::class, 'update'])->name('utils.key-value.update');
    Route::post('utils/key-value/search', [KeyValueCrudController::class, 'search'])->name('utils.key-value.search');
    Route::get('utils/key-value/{id}/details', [KeyValueCrudController::class, 'showDetailsRow'])->name('utils.key-value.details');

    // =========== UTILS: KEYWORD MASTER ==========
    Route::get('utils/keyword-master', [KeywordMasterCrudController::class, 'index'])->name('utils.keyword-master.index');
    Route::post('utils/keyword-master', [KeywordMasterCrudController::class, 'store'])->name('utils.keyword-master.store');
    Route::get('utils/keyword-master/create', [KeywordMasterCrudController::class, 'create'])->name('utils.keyword-master.create');
    Route::get('utils/keyword-master/{id}/edit', [KeywordMasterCrudController::class, 'edit'])->name('utils.keyword-master.edit');
    Route::put('utils/keyword-master/{id}', [KeywordMasterCrudController::class, 'update'])->name('utils.keyword-master.update');
    Route::post('utils/keyword-master/search', [KeywordMasterCrudController::class, 'search'])->name('utils.keyword-master.search');
    Route::get('utils/keyword-master/{id}/details', [KeywordMasterCrudController::class, 'showDetailsRow'])->name('utils.keyword-master.details');

    // =========== SPARES: SPARE REQUEST ==========
    // Same 'operation' key requirement as system-setting above — see BUG-064.
    Route::get('spares/spare-request', ['uses' => SpareRequestCrudController::class.'@index', 'as' => 'spares.spare-request.index', 'operation' => 'list']);
    Route::post('spares/spare-request', ['uses' => SpareRequestCrudController::class.'@store', 'as' => 'spares.spare-request.store', 'operation' => 'create']);
    Route::get('spares/spare-request/create', ['uses' => SpareRequestCrudController::class.'@create', 'as' => 'spares.spare-request.create', 'operation' => 'create']);
    Route::get('spares/spare-request/{id}/edit', ['uses' => SpareRequestCrudController::class.'@edit', 'as' => 'spares.spare-request.edit', 'operation' => 'update']);
    Route::put('spares/spare-request/{id}', ['uses' => SpareRequestCrudController::class.'@update', 'as' => 'spares.spare-request.update', 'operation' => 'update']);
    Route::delete('spares/spare-request/{id}', [SpareRequestCrudController::class, 'destroy'])->name('spares.spare-request.destroy');
    Route::post('spares/spare-request/search', ['uses' => SpareRequestCrudController::class.'@search', 'as' => 'spares.spare-request.search', 'operation' => 'list']);
    Route::get('spares/spare-request/{id}/details', ['uses' => SpareRequestCrudController::class.'@showDetailsRow', 'as' => 'spares.spare-request.details', 'operation' => 'list']);

    // =========== ACCOUNTS: RECEIPT ==========
    Route::get('accounts/receipt', [ReceiptCrudController::class, 'index'])->name('accounts.receipt.index');
    Route::post('accounts/receipt', [ReceiptCrudController::class, 'store'])->name('accounts.receipt.store');
    Route::get('accounts/receipt/create', [ReceiptCrudController::class, 'create'])->name('accounts.receipt.create');
    Route::get('accounts/receipt/{id}/edit', [ReceiptCrudController::class, 'edit'])->name('accounts.receipt.edit');
    Route::put('accounts/receipt/{id}', [ReceiptCrudController::class, 'update'])->name('accounts.receipt.update');
    // accounts.receipt.destroy removed: no destroy() and no delete button (DEC-020).
    Route::get('accounts/receipt/{id}/show', [ReceiptCrudController::class, 'show'])->name('accounts.receipt.show');
    Route::get('accounts/receipt/fetch-enquiry', [ReceiptCrudController::class, 'fetchEnquiryDetails'])
        ->name('accounts.receipt.fetch-enquiry');

    // =========== ACCOUNTS: JOURNAL VOUCHER ==========
    Route::get('accounts/journal-voucher', [JournalVoucherCrudController::class, 'index'])->name('accounts.journal-voucher.index');
    Route::post('accounts/journal-voucher', [JournalVoucherCrudController::class, 'store'])->name('accounts.journal-voucher.store');
    Route::get('accounts/journal-voucher/create', [JournalVoucherCrudController::class, 'create'])->name('accounts.journal-voucher.create');
    Route::get('accounts/journal-voucher/{id}/edit', [JournalVoucherCrudController::class, 'edit'])->name('accounts.journal-voucher.edit');
    Route::put('accounts/journal-voucher/{id}', [JournalVoucherCrudController::class, 'update'])->name('accounts.journal-voucher.update');
    Route::get('accounts/journal-voucher/fetch-enquiry', [JournalVoucherCrudController::class, 'fetchEnquiryDetails'])->name('accounts.journal-voucher.fetch-enquiry');

    // ==================== LEAD ====================
    Route::get('sales/lead', [LeadCrudController::class, 'index'])->name('sales.lead.index');
    Route::get('sales/lead/create', [LeadCrudController::class, 'create'])->name('sales.lead.create');
    Route::post('sales/lead', [LeadCrudController::class, 'store'])->name('sales.lead.store');
    Route::get('sales/lead/{id}/edit', [LeadCrudController::class, 'edit'])->name('sales.lead.edit');
    Route::put('sales/lead/{id}', [LeadCrudController::class, 'update'])->name('sales.lead.update');
    Route::delete('sales/lead/{id}', [LeadCrudController::class, 'destroy'])->name('sales.lead.destroy');
    Route::post('sales/lead/search', [LeadCrudController::class, 'search'])->name('sales.lead.search');
    Route::get('sales/lead/{id}/details', [LeadCrudController::class, 'showDetailsRow'])->name('sales.lead.details');
    Route::get('sales/lead/models/{segmentCode}', [LeadCrudController::class, 'getModels'])->name('sales.lead.get-models');
    Route::get('sales/lead/variants/{modelCode}', [LeadCrudController::class, 'getVariants'])->name('sales.lead.get-variants');
    Route::get('sales/lead/colors/{variantCode}', [LeadCrudController::class, 'getColors'])->name('sales.lead.get-colors');

    // =========== LEAD SOURCE ===================
    Route::get('sales/lead-source', [LeadSourceCrudController::class, 'index'])->name('sales.lead-source.index');
    Route::get('sales/lead-source/create', [LeadSourceCrudController::class, 'create'])->name('sales.lead-source.create');
    Route::post('sales/lead-source', [LeadSourceCrudController::class, 'store'])->name('sales.lead-source.store');
    Route::get('sales/lead-source/{id}/edit', [LeadSourceCrudController::class, 'edit'])->name('sales.lead-source.edit');
    Route::put('sales/lead-source/{id}', [LeadSourceCrudController::class, 'update'])->name('sales.lead-source.update');
    Route::delete('sales/lead-source/{id}', [LeadSourceCrudController::class, 'destroy'])->name('sales.lead-source.destroy');
    Route::post('sales/lead-source/search', [LeadSourceCrudController::class, 'search'])->name('sales.lead-source.search');
    Route::get('sales/lead-source/{id}/details', [LeadSourceCrudController::class, 'showDetailsRow'])->name('sales.lead-source.details');
    Route::get('sales/lead-source/check-code', [LeadSourceCrudController::class, 'checkCode'])->name('sales.lead-source.check-code');

    // =========== ENQUIRY ========================
    // Core CRUD (previously via Route::crud('enquiry', ...), registered explicitly here — see
    // .ai/rules/module-structure.md; Route::crud() can't produce a slash-separated URL alongside a
    // dot-separated route name from a single $name argument, confirmed during batch 30).
    Route::get('sales/enquiry', [EnquiryCrudController::class, 'index'])->name('sales.enquiry.index');
    Route::get('sales/enquiry/create', [EnquiryCrudController::class, 'create'])->name('sales.enquiry.create');
    Route::post('sales/enquiry', [EnquiryCrudController::class, 'store'])->name('sales.enquiry.store');
    Route::get('sales/enquiry/{id}/edit', [EnquiryCrudController::class, 'edit'])->name('sales.enquiry.edit');
    Route::put('sales/enquiry/{id}', [EnquiryCrudController::class, 'update'])->name('sales.enquiry.update');
    Route::delete('sales/enquiry/{id}', [EnquiryCrudController::class, 'destroy'])->name('sales.enquiry.destroy');
    // BUG-094: search()/showDetailsRow() delegate to Backpack's own ListOperation trait methods
    // (traitSearch()/traitShowDetailsRow()), which only run setupListOperation() — and therefore
    // only pick up setListView('admin.sales.enquiry.list') — when the route carries the 'operation' key.
    // Same class of bug as BUG-064/BUG-091; see known-bugs-report.md.
    Route::post('sales/enquiry/search', ['uses' => EnquiryCrudController::class.'@search', 'as' => 'sales.enquiry.search', 'operation' => 'list']);
    Route::get('sales/enquiry/{id}/details', ['uses' => EnquiryCrudController::class.'@showDetailsRow', 'as' => 'sales.enquiry.details', 'operation' => 'list']);

    // Data / Export — 'grid-data' and 'export' are the real, working data()/export() methods;
    // 'grid-data-legacy'/'export-legacy' are BUG-046's confirmed-broken gridData()/exportData()
    // methods, kept registered (still broken) only because they existed before, not because they
    // work — do not treat their presence as confirmation they're functional.
    Route::post('sales/enquiry/grid-data', [EnquiryCrudController::class, 'data'])->name('sales.enquiry.grid-data');
    Route::match(['get', 'post'], 'sales/enquiry/grid-data-legacy', [EnquiryCrudController::class, 'gridData'])->name('sales.enquiry.grid-data-legacy');
    Route::get('sales/enquiry/export', [EnquiryCrudController::class, 'export'])->name('sales.enquiry.export');
    Route::get('sales/enquiry/export-legacy', [EnquiryCrudController::class, 'exportData'])->name('sales.enquiry.export-legacy');

    // AJAX Lookups for Enquiry
    Route::get('sales/enquiry/lead/{leadNo}', [EnquiryCrudController::class, 'getLead'])->name('sales.enquiry.get-lead');
    Route::get('sales/enquiry/variants/{modelCode}', [EnquiryCrudController::class, 'getVariants'])->name('sales.enquiry.get-variants');
    Route::get('sales/enquiry/colors/{variantCode}', [EnquiryCrudController::class, 'getColors'])->name('sales.enquiry.get-colors');
    Route::get('sales/enquiry/models/{segmentCode}', [EnquiryCrudController::class, 'getModels'])->name('sales.enquiry.get-models');
    Route::get('sales/enquiry/sources', [EnquiryCrudController::class, 'getSources'])->name('sales.enquiry.get-sources');
    Route::get('sales/enquiry/sales-consultants', [EnquiryCrudController::class, 'getSalesConsultants'])->name('sales.enquiry.get-sales-consultants');
    // See known-bugs-report.md BUG-058: was registered at 'admin/master/{keyword}/{parent}' inside
    // a group already prefixed with 'admin', producing a literal 'admin/admin/master/...' URL —
    // fixed as part of this move since it's the same line being renamed anyway.
    Route::get('sales/enquiry/master/{keyword}/{parent}', [EnquiryCrudController::class, 'getKeywordValues'])->name('sales.enquiry.master-keyword-values');
    Route::get('sales/enquiry/locations/{branchCode}', [EnquiryCrudController::class, 'getLocations'])->name('sales.enquiry.locations');
    Route::get('sales/enquiry/reference-users', [EnquiryCrudController::class, 'getReferenceUsers'])->name('sales.enquiry.reference-users');
    Route::get('sales/enquiry/check-duplicate', [EnquiryCrudController::class, 'checkDuplicateEnquiry'])->name('sales.enquiry.check-duplicate');
    Route::get('sales/enquiry/location-by-pincode', [EnquiryCrudController::class, 'locationByPincode'])->name('sales.enquiry.location-by-pincode');

    // Specialized Enquiry Listings
    Route::get('sales/enquiry/xceler8', [EnquiryCrudController::class, 'xceler8List'])->name('sales.enquiry.xceler8');
    Route::get('sales/enquiry/hyperlocal', [EnquiryCrudController::class, 'hyperlocalList'])->name('sales.enquiry.hyperlocal');
    Route::get('sales/enquiry/reference', [EnquiryCrudController::class, 'referenceList'])->name('sales.enquiry.reference');
    Route::get('sales/enquiry/virtual-number', [EnquiryCrudController::class, 'virtualNumberList'])->name('sales.enquiry.virtual-number');
    Route::get('sales/enquiry/whatsapp-campaign', [EnquiryCrudController::class, 'whatsappCampaignList'])->name('sales.enquiry.whatsapp-campaign');
    Route::get('sales/enquiry/assigned-long', [EnquiryCrudController::class, 'assignedLongList'])->name('sales.enquiry.assigned-long');
    Route::get('sales/enquiry/unassigned-long', [EnquiryCrudController::class, 'unassignedLongList'])->name('sales.enquiry.unassigned-long');
    Route::get('sales/enquiry/assigned-quick', [EnquiryCrudController::class, 'assignedQuickList'])->name('sales.enquiry.assigned-quick');
    Route::get('sales/enquiry/unassigned-quick', [EnquiryCrudController::class, 'unassignedQuickList'])->name('sales.enquiry.unassigned-quick');
    // 'pending'/'erroneous' point at BUG-046's confirmed-broken pendingList()/erroneousList() methods.
    // sales.enquiry.pending removed: pendingList() never existed and nothing links to it (DEC-020).
    Route::get('sales/enquiry/erroneous', [EnquiryCrudController::class, 'erroneousList'])->name('sales.enquiry.erroneous');

    // Reference Forms
    Route::get('sales/enquiry/reference/create', [EnquiryCrudController::class, 'createReference'])->name('sales.enquiry.reference.create');
    Route::post('sales/enquiry/reference/store', [EnquiryCrudController::class, 'storeReference'])->name('sales.enquiry.reference.store');

    // Import / Status
    Route::post('sales/enquiry/import', [EnquiryCrudController::class, 'importEnquiries'])->name('sales.enquiry.import');
    Route::get('sales/enquiry/import/status/{id}', [EnquiryCrudController::class, 'importStatus'])->name('sales.enquiry.import.status');
    Route::get('sales/enquiry/import/history', [EnquiryCrudController::class, 'importHistory'])->name('sales.enquiry.import.history');

    // =========================================================
    // EXCHANGE & SCRAPPAGE ENQUIRY ROUTES
    // =========================================================
    Route::get('sales/enquiry/exchange/int-in-exchange', [EnquiryCrudController::class, 'exchangeEnquiryList'])->name('sales.enquiry.exchange.int-in-exchange');
    Route::get('sales/enquiry/exchange/int-in-scrappage', [EnquiryCrudController::class, 'scrappageEnquiryList'])->name('sales.enquiry.exchange.int-in-scrappage');
    Route::get('sales/enquiry/exchange/not-interested', [EnquiryCrudController::class, 'exchangeNotInterestedList'])->name('sales.enquiry.exchange.not-interested');
    Route::get('sales/enquiry/exchange/{id}/edit', [EnquiryCrudController::class, 'exchangeEnquiryEdit'])->name('sales.enquiry.exchange.edit');
    Route::post('sales/enquiry/{id}/exchange-update', [EnquiryCrudController::class, 'exchangeEnquiryUpdate'])->name('sales.enquiry.exchange.update');

    // =========================================================
    // FINANCE ENQUIRY ROUTES
    // =========================================================
    Route::get('sales/enquiry/finance/int-in-finance', [EnquiryCrudController::class, 'financeEnquiryList'])->name('sales.enquiry.finance.int-in-finance');
    Route::get('sales/enquiry/finance/not-interested', [EnquiryCrudController::class, 'financeNotInterestedList'])->name('sales.enquiry.finance.not-interested');
    Route::get('sales/enquiry/finance/{id}/edit', [EnquiryCrudController::class, 'financeEnquiryEdit'])->name('sales.enquiry.finance.edit');
    Route::put('sales/enquiry/finance/{id}/update', [EnquiryCrudController::class, 'financeEnquiryUpdate'])->name('sales.enquiry.finance.update');

    // =========== CAMPAIGN ========================
    Route::get('sales/campaign', [CampaignCrudController::class, 'index'])->name('sales.campaign.index');
    Route::get('sales/campaign/create', [CampaignCrudController::class, 'create'])->name('sales.campaign.create');
    Route::post('sales/campaign', [CampaignCrudController::class, 'store'])->name('sales.campaign.store');
    Route::get('sales/campaign/{id}/edit', [CampaignCrudController::class, 'edit'])->name('sales.campaign.edit');
    Route::put('sales/campaign/{id}', [CampaignCrudController::class, 'update'])->name('sales.campaign.update');
    Route::delete('sales/campaign/{id}', [CampaignCrudController::class, 'destroy'])->name('sales.campaign.destroy');
    Route::get('sales/campaign/models/{segmentCode}', [CampaignCrudController::class, 'getModels'])->name('sales.campaign.get-models');
    Route::get('sales/campaign/locations/{branchCode}', [CampaignCrudController::class, 'getLocations'])->name('sales.campaign.get-locations');

    // =========== TEST DRIVE =====================
    // Route::crud('testdrive', 'TestDriveCrudController');
    // =========== ENQUIRY: OTF BOOKINGS ===========
    // (hyperlocal was a duplicate of the registration above, removed)
    Route::get('sales/enquiry/otf-bookings', [EnquiryCrudController::class, 'otfBookingsList'])
        ->name('sales.enquiry.otf-bookings');
    Route::get('sales/enquiry/otf-bookings/{id}/show', [EnquiryCrudController::class, 'showOtf'])->name('sales.enquiry.otf.show');
}); // ← This should be the last line

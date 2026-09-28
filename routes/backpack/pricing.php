<?php

use App\Http\Controllers\Admin\Pricing\HoldController;
use App\Http\Controllers\Admin\Pricing\InsuranceController;
use App\Http\Controllers\Admin\Pricing\PricingResetController;
use App\Http\Controllers\Admin\Pricing\PricingWorkflowController;
use App\Http\Controllers\Admin\Pricing\Process\AddonsController;
use App\Http\Controllers\Admin\Pricing\Process\PricesController;
use App\Http\Controllers\Admin\Pricing\Process\PricingProcessController;
use App\Http\Controllers\Admin\Pricing\Process\VehicleInfoController;
use App\Http\Controllers\Admin\Pricing\RtoRuleController;
use App\Http\Controllers\Admin\Pricing\TcsConfigController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'admin/pricing',
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
], function () {

    Route::get('tcs', [TcsConfigController::class, 'index'])->name('pricing.tcs.index');
    Route::put('tcs', [TcsConfigController::class, 'update'])->name('pricing.tcs.update');

    Route::get('hold', [HoldController::class, 'index'])->name('pricing.hold.index');
    Route::post('hold', [HoldController::class, 'hold'])->name('pricing.hold.apply');
    Route::post('hold/reopen', [HoldController::class, 'reopen'])->name('pricing.hold.reopen');

    Route::get('rto', [RtoRuleController::class, 'index'])->name('pricing.rto.index');
    Route::get('rto/create', [RtoRuleController::class, 'create'])->name('pricing.rto.create');
    Route::post('rto', [RtoRuleController::class, 'store'])->name('pricing.rto.store');
    Route::get('rto/{id}/edit', [RtoRuleController::class, 'edit'])->name('pricing.rto.edit');
    Route::put('rto/{id}', [RtoRuleController::class, 'update'])->name('pricing.rto.update');
    Route::post('rto/test-calculate', [RtoRuleController::class, 'testCalculate'])->name('pricing.rto.test');

    Route::get('insurance/defaults', [InsuranceController::class, 'defaultsIndex'])->name('pricing.insurance.defaults');
    Route::get('insurance/base-rules', [InsuranceController::class, 'baseRulesIndex'])->name('pricing.insurance.base-rules');
    Route::get('insurance/addon-rates', [InsuranceController::class, 'addonRatesIndex'])->name('pricing.insurance.addon-rates');
    Route::post('insurance/test-calculate', [InsuranceController::class, 'testCalculate'])->name('pricing.insurance.test');

    // DEC-073 pricing process — steps 0–2 (gate, start, detect)
    Route::get('workflow', [PricingProcessController::class, 'index'])->name('pricing.workflow.index');
    Route::get('workflow/start', [PricingProcessController::class, 'startForm'])->name('pricing.workflow.start-form');
    Route::post('workflow/start', [PricingProcessController::class, 'start'])->name('pricing.workflow.start');
    Route::get('workflow/status/{sessionId}', [PricingProcessController::class, 'status'])->whereNumber('sessionId')->name('pricing.workflow.status');

    Route::get('workflow/vehicle-info', [VehicleInfoController::class, 'show'])->name('pricing.workflow.vehicle-info-form');
    Route::get('workflow/vehicle-info-export/{sessionId}', [VehicleInfoController::class, 'export'])->whereNumber('sessionId')->name('pricing.workflow.vehicle-info-export');
    Route::post('workflow/vehicle-info-import', [VehicleInfoController::class, 'import'])->name('pricing.workflow.vehicle-info-import');
    Route::get('workflow/vehicle-info-issues/{sessionId}', [VehicleInfoController::class, 'issues'])->whereNumber('sessionId')->name('pricing.workflow.vehicle-info-issues');
    Route::post('workflow/vehicle-info-continue', [VehicleInfoController::class, 'continue'])->name('pricing.workflow.vehicle-info-continue');

    Route::get('workflow/prices', [PricesController::class, 'show'])->name('pricing.workflow.prices-form');
    Route::post('workflow/prices', [PricesController::class, 'import'])->name('pricing.workflow.prices');
    Route::get('workflow/prices-issues/{sessionId}', [PricesController::class, 'issues'])->whereNumber('sessionId')->name('pricing.workflow.prices-issues');
    Route::post('workflow/prices-continue', [PricesController::class, 'continue'])->name('pricing.workflow.prices-continue');

    Route::get('workflow/addons', [AddonsController::class, 'show'])->name('pricing.workflow.addons-form');
    Route::get('workflow/addons-export/{sessionId}', [AddonsController::class, 'export'])->whereNumber('sessionId')->name('pricing.workflow.addons-export');
    Route::post('workflow/addons', [AddonsController::class, 'import'])->name('pricing.workflow.addons');
    Route::get('workflow/addons-issues/{sessionId}', [AddonsController::class, 'issues'])->whereNumber('sessionId')->name('pricing.workflow.addons-issues');
    Route::post('workflow/addons-continue', [AddonsController::class, 'continue'])->name('pricing.workflow.addons-continue');

    Route::get('workflow/rules', [PricingWorkflowController::class, 'rulesForm'])->name('pricing.workflow.rules-form');
    Route::get('workflow/rules-export/{sessionId}', [PricingWorkflowController::class, 'rulesExport'])->name('pricing.workflow.rules-export');
    Route::post('workflow/rules-keep', [PricingWorkflowController::class, 'rulesKeep'])->name('pricing.workflow.rules-keep');
    Route::post('workflow/rules', [PricingWorkflowController::class, 'rulesImport'])->name('pricing.workflow.rules');

    Route::post('workflow/discard', [PricingProcessController::class, 'discard'])->name('pricing.workflow.discard');

    Route::get('workflow/impact-summary/{sessionId}', [PricingWorkflowController::class, 'impactSummary'])->name('pricing.workflow.impact-summary');
    Route::get('workflow/impact-summary-view/{sessionId}', [PricingWorkflowController::class, 'impactSummaryView'])->name('pricing.workflow.impact-summary-view');
    Route::post('workflow/calculate/{sessionId}', [PricingWorkflowController::class, 'calculateAndPublish'])->name('pricing.workflow.calculate');
    Route::get('workflow/session-status/{sessionId}', [PricingWorkflowController::class, 'sessionStatus'])->name('pricing.workflow.session-status');
    Route::get('workflow/failed-vehicles/{sessionId}', [PricingWorkflowController::class, 'failedVehicles'])->name('pricing.workflow.failed-vehicles');

    // RESET To Date
    // For Preview Only : http://xlrm.test/admin/pricing/reset?after=2026-08-20&confirm=1
    // For Actual Run : http://xlrm.test/admin/pricing/reset?after=2026-08-20

    Route::get('reset', PricingResetController::class)->name('pricing.reset');
});

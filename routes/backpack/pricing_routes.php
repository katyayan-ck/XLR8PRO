<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Pricing\PricingWorkflowController;
use App\Http\Controllers\Admin\Pricing\TcsConfigController;
use App\Http\Controllers\Admin\Pricing\HoldController;
use App\Http\Controllers\Admin\Pricing\RtoRuleController;
use App\Http\Controllers\Admin\Pricing\InsuranceController;

Route::group([
    'prefix'     => 'admin/pricing',
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
], function () {

    // TCS
    Route::get('tcs', [TcsConfigController::class, 'index'])->name('pricing.tcs.index');
    Route::put('tcs', [TcsConfigController::class, 'update'])->name('pricing.tcs.update');

    // Price Hold
    Route::get('hold', [HoldController::class, 'index'])->name('pricing.hold.index');
    Route::post('hold', [HoldController::class, 'hold'])->name('pricing.hold.apply');
    Route::post('hold/reopen', [HoldController::class, 'reopen'])->name('pricing.hold.reopen');

    // RTO
    Route::get('rto', [RtoRuleController::class, 'index'])->name('pricing.rto.index');
    Route::get('rto/create', [RtoRuleController::class, 'create'])->name('pricing.rto.create');
    Route::post('rto', [RtoRuleController::class, 'store'])->name('pricing.rto.store');
    Route::get('rto/{id}/edit', [RtoRuleController::class, 'edit'])->name('pricing.rto.edit');
    Route::put('rto/{id}', [RtoRuleController::class, 'update'])->name('pricing.rto.update');
    Route::post('rto/test-calculate', [RtoRuleController::class, 'testCalculate'])->name('pricing.rto.test');

    // Insurance
    Route::get('insurance/defaults', [InsuranceController::class, 'defaultsIndex'])->name('pricing.insurance.defaults');
    Route::get('insurance/base-rules', [InsuranceController::class, 'baseRulesIndex'])->name('pricing.insurance.base-rules');
    Route::get('insurance/addon-rates', [InsuranceController::class, 'addonRatesIndex'])->name('pricing.insurance.addon-rates');
    Route::post('insurance/test-calculate', [InsuranceController::class, 'testCalculate'])->name('pricing.insurance.test');

    // Workflow (FRS v3.1)
    Route::get('workflow', [PricingWorkflowController::class, 'index'])
        ->name('pricing.workflow.index');

    Route::get('workflow/start', [PricingWorkflowController::class, 'startForm'])
        ->name('pricing.workflow.start-form');
    Route::post('workflow/start', [PricingWorkflowController::class, 'startDetect'])
        ->name('pricing.workflow.start');
    Route::get('workflow/progress/{sessionId}', [PricingWorkflowController::class, 'progress'])
        ->name('pricing.workflow.progress');

    Route::get('workflow/vehicle-info', [PricingWorkflowController::class, 'vehicleInfoForm'])
        ->name('pricing.workflow.vehicle-info-form');
    Route::get('workflow/vehicle-info-export/{sessionId}', [PricingWorkflowController::class, 'vehicleInfoExport'])
        ->name('pricing.workflow.vehicle-info-export');
    Route::post('workflow/vehicle-info-import', [PricingWorkflowController::class, 'vehicleInfoImport'])
        ->name('pricing.workflow.vehicle-info-import');

    Route::get('workflow/prices', [PricingWorkflowController::class, 'pricesForm'])
        ->name('pricing.workflow.prices-form');
    Route::post('workflow/prices', [PricingWorkflowController::class, 'pricesImport'])
        ->name('pricing.workflow.prices');

    Route::post('workflow/discard', [PricingWorkflowController::class, 'discard'])
        ->name('pricing.workflow.discard');

    Route::get('workflow/impact-summary/{sessionId}', [PricingWorkflowController::class, 'impactSummary'])
        ->name('pricing.workflow.impact-summary');
    Route::get('workflow/impact-summary-view/{sessionId}', [PricingWorkflowController::class, 'impactSummaryView'])
        ->name('pricing.workflow.impact-summary-view');
    Route::post('workflow/calculate/{sessionId}', [PricingWorkflowController::class, 'calculateAndPublish'])
        ->name('pricing.workflow.calculate');
    Route::get('workflow/session-status/{sessionId}', [PricingWorkflowController::class, 'sessionStatus'])
        ->name('pricing.workflow.session-status');
    Route::get('workflow/failed-vehicles/{sessionId}', [PricingWorkflowController::class, 'failedVehicles'])
        ->name('pricing.workflow.failed-vehicles');
});

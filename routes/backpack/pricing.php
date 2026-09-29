<?php

use App\Http\Controllers\Admin\Pricing\HoldController;
use App\Http\Controllers\Admin\Pricing\InsuranceController;
use App\Http\Controllers\Admin\Pricing\MasterController;
use App\Http\Controllers\Admin\Pricing\PriceListController;
use App\Http\Controllers\Admin\Pricing\PriceLookupController;
use App\Http\Controllers\Admin\Pricing\PricingResetController;
use App\Http\Controllers\Admin\Pricing\Process\AddonsController;
use App\Http\Controllers\Admin\Pricing\Process\CalculateController;
use App\Http\Controllers\Admin\Pricing\Process\ImpactController;
use App\Http\Controllers\Admin\Pricing\Process\PricesController;
use App\Http\Controllers\Admin\Pricing\Process\PricingProcessController;
use App\Http\Controllers\Admin\Pricing\Process\RulesController;
use App\Http\Controllers\Admin\Pricing\Process\VehicleInfoController;
use App\Http\Controllers\Admin\Pricing\RecalcLogController;
use App\Http\Controllers\Admin\Pricing\RtoRuleController;
use App\Http\Controllers\Admin\Pricing\TcsConfigController;
use App\Support\PricingMaster\MasterRegistry;
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

    Route::get('lookup', [PriceLookupController::class, 'index'])->name('pricing.lookup');

    Route::get('recalculation-log', [RecalcLogController::class, 'index'])->name('pricing.recalc-log');

    // DEC-083 pricing masters — list / CRUD / import / export (one controller, a definition per master)
    Route::prefix('masters/{master}')->whereIn('master', MasterRegistry::keys())->name('pricing.masters.')->group(function () {
        Route::get('/', [MasterController::class, 'index'])->name('index');
        Route::get('rows', [MasterController::class, 'rows'])->name('rows');
        Route::get('create', [MasterController::class, 'create'])->name('create');
        Route::post('/', [MasterController::class, 'store'])->name('store');
        Route::get('export', [MasterController::class, 'export'])->name('export');
        Route::post('import', [MasterController::class, 'import'])->name('import');
        Route::get('imports/{id}', [MasterController::class, 'importStatus'])->whereNumber('id')->name('import-status');
        Route::get('{id}/edit', [MasterController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('{id}', [MasterController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('{id}', [MasterController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });

    // DEC-081 standalone Price List — every logged-in user, read-only
    Route::get('price-list', [PriceListController::class, 'index'])->name('pricing.price-list.index');
    Route::get('price-list/{list}', [PriceListController::class, 'show'])->where('list', 'pv|taxi|cv|bev|lmm|tzu|csd')->name('pricing.price-list.show');
    Route::get('price-list/{list}/rows', [PriceListController::class, 'rows'])->where('list', 'pv|taxi|cv|bev|lmm|tzu|csd')->name('pricing.price-list.rows');

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

    Route::get('workflow/rules', [RulesController::class, 'show'])->name('pricing.workflow.rules-form');
    Route::get('workflow/rules-export/{kind}/{sessionId}', [RulesController::class, 'export'])->whereIn('kind', ['insurance', 'rto'])->whereNumber('sessionId')->name('pricing.workflow.rules-export');
    Route::post('workflow/rules', [RulesController::class, 'import'])->name('pricing.workflow.rules');
    Route::get('workflow/rules-issues/{kind}/{sessionId}', [RulesController::class, 'issues'])->whereIn('kind', ['insurance', 'rto'])->whereNumber('sessionId')->name('pricing.workflow.rules-issues');
    Route::post('workflow/rules-continue', [RulesController::class, 'continue'])->name('pricing.workflow.rules-continue');

    Route::post('workflow/discard', [PricingProcessController::class, 'discard'])->name('pricing.workflow.discard');

    Route::get('workflow/impact-summary-view/{sessionId}', [ImpactController::class, 'show'])->whereNumber('sessionId')->name('pricing.workflow.impact-summary-view');
    Route::get('workflow/impact-incomplete/{sessionId}', [ImpactController::class, 'incomplete'])->whereNumber('sessionId')->name('pricing.workflow.impact-incomplete');
    Route::post('workflow/impact-continue', [ImpactController::class, 'continue'])->name('pricing.workflow.impact-continue');
    Route::post('workflow/hold-check', [ImpactController::class, 'hold'])->name('pricing.workflow.hold-check');
    Route::post('workflow/calculate-start', [CalculateController::class, 'start'])->name('pricing.workflow.calculate-start');
    Route::get('workflow/summary/{sessionId}', [CalculateController::class, 'summary'])->whereNumber('sessionId')->name('pricing.workflow.summary');
    Route::get('workflow/summary-results/{sessionId}', [CalculateController::class, 'results'])->whereNumber('sessionId')->name('pricing.workflow.summary-results');
    Route::post('workflow/retry-failed', [CalculateController::class, 'retry'])->name('pricing.workflow.retry-failed');
    Route::post('workflow/complete', [CalculateController::class, 'complete'])->name('pricing.workflow.complete');

    // Pricing reset (DEC-082): GET = dry preview + confirmation form; POST = run (PRC_RESET_MANAGE, local only)
    Route::get('reset', [PricingResetController::class, 'preview'])->name('pricing.reset');
    Route::post('reset', [PricingResetController::class, 'run'])->name('pricing.reset.run');
});

<?php

use App\Http\Controllers\Admin\Org\User\UserImportExportController;
use App\Http\Controllers\Admin\PerformanceController;
use App\Http\Controllers\Demo\DemoRbacController;
use App\Http\Controllers\ExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::middleware(array_merge(
    (array) config('backpack.base.web_middleware', 'web'),
    (array) config('backpack.base.middleware_key', 'admin')
))->group(function () {
    Route::get('/performance-report', [PerformanceController::class, 'report']);
    Route::prefix('export')->group(function () {
        Route::get('vehicle-data', [ExportController::class, 'vehicleDataExcel'])
            ->name('export.vehicle.excel');

        Route::get('vehicle-data-csv', [ExportController::class, 'vehicleDataCsv'])
            ->name('export.vehicle.csv');

        Route::get('vehicle-data-simple-csv', [ExportController::class, 'vehicleDataSimpleCsv'])
            ->name('export.vehicle.simple-csv');
    });
});
Route::middleware(array_merge(
    (array) config('backpack.base.web_middleware', 'web'),
    (array) config('backpack.base.middleware_key', 'admin')
))->group(function () {
    Route::prefix('admin/org/user')->group(function () {
        // Import routes
        Route::get('/import', [UserImportExportController::class, 'showImportForm'])->name('org.user.import');
        Route::post('/import', [UserImportExportController::class, 'import'])->name('org.user.import.process');
        Route::get('/import/history', [UserImportExportController::class, 'importHistory'])->name('org.user.import.history');
        Route::get('/import/template', [UserImportExportController::class, 'downloadTemplate'])->name('org.user.import.template');

        // Export routes
        Route::get('/export', [UserImportExportController::class, 'showExportForm'])->name('org.user.export');
        Route::post('/export', [UserImportExportController::class, 'export'])->name('org.user.export.process');
        Route::get('/export/history', [UserImportExportController::class, 'exportHistory'])->name('org.user.export.history');
    });
});

// ==================== UI/UX MOCKUP — Module/Process/Permission role + user-override
// management screens. Real Module/Process/Permission tree data; spoofed role/user
// assignment JSON. Nothing here persists — see DemoRbacController's docblock. ====================
Route::middleware(array_merge(
    (array) config('backpack.base.web_middleware', 'web'),
    (array) config('backpack.base.middleware_key', 'admin')
))->prefix('demo')->name('demo.')->group(function () {
    Route::get('roles', [DemoRbacController::class, 'roles'])->name('roles');
    Route::get('users', [DemoRbacController::class, 'users'])->name('users');
});

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\PerformanceController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\UserImportExportController;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/performance-report', [PerformanceController::class, 'report']);
Route::prefix('export')->group(function () {
    Route::get('vehicle-data', [ExportController::class, 'vehicleDataExcel'])
        ->name('export.vehicle.excel');

    Route::get('vehicle-data-csv', [ExportController::class, 'vehicleDataCsv'])
        ->name('export.vehicle.csv');

    Route::get('vehicle-data-simple-csv', [ExportController::class, 'vehicleDataSimpleCsv'])
        ->name('export.vehicle.simple-csv');
});
Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('admin/users')->group(function () {
        // Import routes
        Route::get('/import', [UserImportExportController::class, 'showImportForm'])->name('users.import');
        Route::post('/import', [UserImportExportController::class, 'import'])->name('users.import.process');
        Route::get('/import/history', [UserImportExportController::class, 'importHistory'])->name('users.import.history');
        Route::get('/import/template', [UserImportExportController::class, 'downloadTemplate'])->name('users.import.template');

        // Export routes
        Route::get('/export', [UserImportExportController::class, 'showExportForm'])->name('users.export');
        Route::post('/export', [UserImportExportController::class, 'export'])->name('users.export.process');
        Route::get('/export/history', [UserImportExportController::class, 'exportHistory'])->name('users.export.history');
    });
});

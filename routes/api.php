<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\SystemSettingApiController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\DocController;
use App\Http\Controllers\Api\V1\EntityHistoryController;
use App\Http\Controllers\Api\V1\Vehicle\Pricing\PricingController;

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('/request-otp', [AuthController::class, 'requestOtp'])
            ->name('api.auth.request-otp');

        Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])
            ->name('api.auth.verify-otp');
    });

    Route::middleware(['auth:sanctum'])->prefix('vehicle/pricing')->group(function () {
        Route::get('{modelCode}', [PricingController::class, 'getPricing'])
            ->name('api.v1.vehicle.pricing.show');
        Route::post('/', [PricingController::class, 'getPricing'])
            ->name('api.v1.vehicle.pricing.show.post');
        Route::get('{modelCode}/live', [PricingController::class, 'getLivePricing'])
            ->name('api.v1.vehicle.pricing.live');
    });

    /*
     * Old quotation helpers. Keep ONLY if this class file still exists
     * AND its namespace is exactly App\Http\Controllers\Api\V1\PricingApiController
     * (not Vehicle\Pricing). If that file was overwritten, comment this block out.
     *
     * Route::middleware(['auth:api'])->group(function () {
     *     Route::post('/pricing/calculate-exchange', [\App\Http\Controllers\Api\V1\PricingApiController::class, 'calculateExchange']);
     *     Route::post('/pricing/generate-quote', [\App\Http\Controllers\Api\V1\PricingApiController::class, 'generateQuote']);
     * });
     */

    Route::middleware(['auth:sanctum', 'validate_device'])->group(function () {

        Route::prefix('auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me'])->name('api.auth.me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        });

        Route::post('docs/upload', [DocController::class, 'upload']);
        Route::get('docs/my', [DocController::class, 'getMyDocs']);
        Route::post('docs/groups', [DocController::class, 'createGroup']);
        Route::post('docs/groups/{groupId}/add', [DocController::class, 'addToGroup']);
        Route::delete('docs/groups/{groupId}/remove/{docId}', [DocController::class, 'removeFromGroup']);
        Route::get('docs/groups/{groupId}/zip', [DocController::class, 'downloadGroupZip']);
        Route::get('docs/search', [DocController::class, 'search']);
        Route::get('docs/analytics', [DocController::class, 'getAnalytics']);
        Route::post('docs/{docId}/approve', [DocController::class, 'approve']);

        Route::get('history/{entityType}/{entityId}', [EntityHistoryController::class, 'getHistory']);
        Route::post('history/{entityType}/{entityId}/thread', [EntityHistoryController::class, 'addThread']);

        Route::post('/devices/register', [NotificationController::class, 'registerDevice']);
        Route::get('/devices', [NotificationController::class, 'getDevices']);
        Route::delete('/devices/{id}', [NotificationController::class, 'revokeDevice']);
        Route::post('/devices/revoke-all', [NotificationController::class, 'revokeAllDevices']);

        Route::get('/notifications', [NotificationController::class, 'getNotifications']);
        Route::get('/notifications/unread', [NotificationController::class, 'getUnreadNotifications']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'deleteNotification']);

        Route::get('/alerts', [NotificationController::class, 'getAlerts']);
        Route::post('/alerts/{id}/read', [NotificationController::class, 'markAlertAsRead']);

        Route::get('/messages/user/{user_id}', [NotificationController::class, 'getConversation']);
        Route::post('/messages/user/{user_id}', [NotificationController::class, 'sendMessage']);
        Route::post('/messages/{id}/read', [NotificationController::class, 'markMessageAsRead']);

        Route::prefix('system-settings')->group(function () {
            Route::get('/', [SystemSettingApiController::class, 'index'])->name('api.settings.index');
            Route::get('topic/{topic}', [SystemSettingApiController::class, 'topic'])->name('api.settings.topic');
            Route::get('category/site', [SystemSettingApiController::class, 'siteSettings'])->name('api.settings.site');
            Route::get('category/dealership', [SystemSettingApiController::class, 'dealershipSettings'])->name('api.settings.dealership');
            Route::get('category/pricing', [SystemSettingApiController::class, 'pricingSettings'])->name('api.settings.pricing');
            Route::get('{key}', [SystemSettingApiController::class, 'show'])->where('key', '.*')->name('api.settings.show');
        });

        Route::middleware('role:admin|super_admin')->group(function () {
            Route::prefix('system-settings')->group(function () {
                Route::get('export/json', [SystemSettingApiController::class, 'exportJson'])->name('api.settings.export.json');
                Route::post('import/json', [SystemSettingApiController::class, 'importJson'])->name('api.settings.import.json');
                Route::put('{key}', [SystemSettingApiController::class, 'update'])->where('key', '.*')->name('api.settings.update');
            });
        });
    });
});

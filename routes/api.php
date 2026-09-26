<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DocController;
use App\Http\Controllers\Api\V1\EntityHistoryController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PricingApiController;
use App\Http\Controllers\Api\V1\SystemSettingApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ╔════════════════════════════════════════════════════════╗
    // ║ PUBLIC AUTH ROUTES (No Authentication Required)       ║
    // ╚════════════════════════════════════════════════════════╝

    Route::prefix('auth')->group(function () {
        Route::post('/request-otp', [AuthController::class, 'requestOtp'])
            ->name('api.auth.request-otp');

        Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])
            ->name('api.auth.verify-otp');
    });

    Route::group(['middleware' => ['auth:sanctum', 'validate_device']], function () {
        Route::post('/pricing/calculate-exchange', [PricingApiController::class, 'calculateExchange']);
        Route::post('/pricing/generate-quote', [PricingApiController::class, 'generateQuote']);
    });

    // ╔════════════════════════════════════════════════════════╗
    // ║ PROTECTED ROUTES (Authentication + Device Validation) ║
    // ╚════════════════════════════════════════════════════════╝

    Route::middleware(['auth:sanctum', 'validate_device'])->group(function () {

        // Auth routes (protected)
        Route::prefix('auth')->group(function () {
            Route::get('/me', [AuthController::class, 'me'])
                ->name('api.auth.me');

            Route::post('/logout', [AuthController::class, 'logout'])
                ->name('api.auth.logout');
        });

        // DocManager Routes
        Route::post('docs/upload', [DocController::class, 'upload']);
        Route::get('docs/my', [DocController::class, 'getMyDocs']);
        Route::post('docs/groups', [DocController::class, 'createGroup']);
        Route::post('docs/groups/{groupId}/add', [DocController::class, 'addToGroup']);
        Route::delete('docs/groups/{groupId}/remove/{docId}', [DocController::class, 'removeFromGroup']);
        Route::get('docs/groups/{groupId}/zip', [DocController::class, 'downloadGroupZip']);
        Route::get('docs/search', [DocController::class, 'search']);
        Route::get('docs/analytics', [DocController::class, 'getAnalytics']);
        Route::post('docs/{docId}/approve', [DocController::class, 'approve']);

        // CommMasters routes (protected)
        Route::get('history/{entityType}/{entityId}', [EntityHistoryController::class, 'getHistory']);
        Route::post('history/{entityType}/{entityId}/thread', [EntityHistoryController::class, 'addThread']);

        Route::post('/devices/register', [NotificationController::class, 'registerDevice']);
        Route::get('/devices', [NotificationController::class, 'getUserDevices']);
        Route::delete('/devices/{id}', [NotificationController::class, 'unregisterDevice']);
        Route::post('/devices/revoke-all', [NotificationController::class, 'revokeAllDevices']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'getNotifications']);
        Route::get('/notifications/unread', [NotificationController::class, 'getUnreadNotifications']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markNotificationAsRead']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllNotificationsAsRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'deleteNotification']);

        // Alerts
        Route::get('/alerts', [NotificationController::class, 'getAlerts']);
        Route::post('/alerts/{id}/read', [NotificationController::class, 'markAlertAsRead']);

        // Messages
        Route::get('/messages/user/{user_id}', [NotificationController::class, 'getConversationMessages']);
        Route::post('/messages/user/{user_id}', [NotificationController::class, 'sendMessage']);
        Route::post('/messages/{id}/read', [NotificationController::class, 'markMessageAsRead']);
        // System Settings routes (protected)
        Route::prefix('system-settings')->group(function () {

            // Get all settings
            Route::get('/', [SystemSettingApiController::class, 'index'])
                ->name('api.settings.index');

            // Get settings by topic
            Route::get('topic/{topic}', [SystemSettingApiController::class, 'getByTopic'])
                ->name('api.settings.topic');

            // Category shortcuts
            Route::get('category/site', [SystemSettingApiController::class, 'siteSettings'])
                ->name('api.settings.site');

            Route::get('category/dealership', [SystemSettingApiController::class, 'dealershipSettings'])
                ->name('api.settings.dealership');

            Route::get('category/pricing', [SystemSettingApiController::class, 'pricingSettings'])
                ->name('api.settings.pricing');

            // Get setting by key (MUST be last - catchall pattern)
            // excludes export/json, which is registered later in the admin group
            Route::get('{key}', [SystemSettingApiController::class, 'show'])
                ->where('key', '(?!export/json$).*')
                ->name('api.settings.show');
        });

        // Admin-only routes
        Route::middleware('permission:UTL_SETTINGS_MANAGE')->group(function () {
            Route::prefix('system-settings')->group(function () {

                // Export/Import
                Route::get('export/json', [SystemSettingApiController::class, 'exportSettings'])
                    ->name('api.settings.export.json');

                Route::post('import/json', [SystemSettingApiController::class, 'importSettings'])
                    ->name('api.settings.import.json');

                // Admin update setting
                Route::put('{key}', [SystemSettingApiController::class, 'update'])
                    ->where('key', '.*')
                    ->name('api.settings.update');
            });
        });
    });
});

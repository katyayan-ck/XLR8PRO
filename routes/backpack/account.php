<?php

use App\Http\Controllers\Admin\Account\MyAccountController;
use App\Http\Controllers\Admin\Account\SessionLockController;
use Illuminate\Support\Facades\Route;

/*
| My Account (DEC-072). Replaces Backpack's own account routes (config backpack.base.setup_my_account_routes = false)
| under the same route names, so existing links keep working. Every action acts on the signed-in user only.
*/
Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin'),
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
], function () {
    Route::get('edit-account-info', [MyAccountController::class, 'show'])->name('backpack.account.info');
    Route::post('edit-account-info', [MyAccountController::class, 'updateProfile'])->name('backpack.account.info.store');
    Route::post('edit-account-info/photo', [MyAccountController::class, 'updatePhoto'])->name('backpack.account.photo');
    Route::post('edit-account-info/personal', [MyAccountController::class, 'updatePersonal'])->name('backpack.account.personal');   // DEC-091
    Route::post('change-password', [MyAccountController::class, 'changePassword'])->name('backpack.account.password');

    // Screen lock + idle heartbeat (go-live to-do S1 / S2); EnforceIdleSession lets these through while locked
    Route::get('session/lock-screen', [SessionLockController::class, 'lockScreen'])->name('xl.session.lock-screen');
    Route::post('session/lock', [SessionLockController::class, 'lock'])->name('xl.session.lock');
    Route::post('session/unlock', [SessionLockController::class, 'unlock'])->middleware('throttle:10,1')->name('xl.session.unlock');
    Route::post('session/activity', [SessionLockController::class, 'activity'])->name('xl.session.activity');
});

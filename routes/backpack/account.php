<?php

use App\Http\Controllers\Admin\Account\MyAccountController;
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
    Route::post('change-password', [MyAccountController::class, 'changePassword'])->name('backpack.account.password');
});

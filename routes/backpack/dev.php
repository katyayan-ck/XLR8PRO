<?php

use App\Http\Controllers\Admin\Dev\UiKitController;
use Illuminate\Support\Facades\Route;

/*
| Developer reference screens (DEC-067). Static Tabler / shared-layer samples; the controller returns 404 unless
| config('platform.dev_ui_kit') is on (local by default).
*/
Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin').'/dev',
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
], function () {
    Route::get('ui/{page?}', [UiKitController::class, 'show'])
        ->whereIn('page', array_keys(UiKitController::PAGES))
        ->name('dev.ui.show');
});

<?php

use App\Http\Controllers\Admin\Utils\Platform\ChatController;
use App\Http\Controllers\Admin\Utils\Platform\DocsLibraryController;
use App\Http\Controllers\Admin\Utils\Platform\NotificationInboxController;
use App\Http\Controllers\Admin\Utils\Platform\SettingsAdminController;
use Illuminate\Support\Facades\Route;

/*
| Platform utilities (FRS v1.1, DEC-061..065). Controllers check permissions inline; the inbox
| and chat endpoints follow the signed-in user / the record's own access rule.
*/
Route::group([
    'prefix' => config('backpack.base.route_prefix', 'admin').'/utils',
    'middleware' => array_merge(
        (array) config('backpack.base.web_middleware', 'web'),
        (array) config('backpack.base.middleware_key', 'admin')
    ),
], function () {
    // Notify inbox
    Route::get('inbox', [NotificationInboxController::class, 'index'])->name('utils.inbox.index');
    Route::post('inbox/mark-all', [NotificationInboxController::class, 'markAll'])->name('utils.inbox.mark-all');
    Route::post('inbox/{kind}/{id}/mark', [NotificationInboxController::class, 'mark'])->whereIn('kind', ['N', 'A', 'M'])->whereNumber('id')->name('utils.inbox.mark');
    Route::get('inbox/{kind}/{id}/open', [NotificationInboxController::class, 'open'])->whereIn('kind', ['N', 'A', 'M'])->whereNumber('id')->name('utils.inbox.open');

    // Chat
    Route::post('chat/remark', [ChatController::class, 'remark'])->name('utils.chat.remark');
    Route::post('chat/subscribe', [ChatController::class, 'subscribe'])->name('utils.chat.subscribe');
    Route::put('chat/remark/{threadId}', [ChatController::class, 'edit'])->whereNumber('threadId')->name('utils.chat.edit');
    Route::delete('chat/remark/{threadId}', [ChatController::class, 'destroy'])->whereNumber('threadId')->name('utils.chat.destroy');

    // Docs library, cart and packs
    Route::get('docs', [DocsLibraryController::class, 'index'])->name('utils.docs.index');
    Route::post('docs', [DocsLibraryController::class, 'store'])->name('utils.docs.store');
    Route::get('docs/{id}/download', [DocsLibraryController::class, 'download'])->whereNumber('id')->name('utils.docs.download');
    Route::delete('docs/{id}', [DocsLibraryController::class, 'destroy'])->whereNumber('id')->name('utils.docs.destroy');
    Route::post('docs/cart/save', [DocsLibraryController::class, 'cartSave'])->name('utils.docs.cart.save');
    Route::post('docs/cart/{id}', [DocsLibraryController::class, 'cartAdd'])->whereNumber('id')->name('utils.docs.cart.add');
    Route::delete('docs/cart/{id}', [DocsLibraryController::class, 'cartRemove'])->whereNumber('id')->name('utils.docs.cart.remove');
    Route::put('docs/packs/{groupId}', [DocsLibraryController::class, 'groupRename'])->whereNumber('groupId')->name('utils.docs.packs.rename');
    Route::delete('docs/packs/{groupId}', [DocsLibraryController::class, 'groupDestroy'])->whereNumber('groupId')->name('utils.docs.packs.destroy');
    Route::get('docs/packs/{groupId}/zip', [DocsLibraryController::class, 'groupZip'])->whereNumber('groupId')->name('utils.docs.packs.zip');

    // Settings
    Route::get('settings', [SettingsAdminController::class, 'index'])->name('utils.settings.index');
    Route::put('settings', [SettingsAdminController::class, 'update'])->name('utils.settings.update');
    Route::post('settings/reset', [SettingsAdminController::class, 'reset'])->name('utils.settings.reset');
});

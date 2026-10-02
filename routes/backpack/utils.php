<?php

use App\Http\Controllers\Admin\Utils\Platform\ApprovalAdminController;
use App\Http\Controllers\Admin\Utils\Platform\ApprovalController;
use App\Http\Controllers\Admin\Utils\Platform\ApprovalReportController;
use App\Http\Controllers\Admin\Utils\Platform\ChatController;
use App\Http\Controllers\Admin\Utils\Platform\CommsController;
use App\Http\Controllers\Admin\Utils\Platform\DocsLibraryController;
use App\Http\Controllers\Admin\Utils\Platform\HelpController;
use App\Http\Controllers\Admin\Utils\Platform\NotificationInboxController;
use App\Http\Controllers\Admin\Utils\Platform\SettingsAdminController;
use App\Http\Controllers\Admin\Utils\Platform\TaskController;
use App\Http\Controllers\Admin\Utils\Platform\TemplateAdminController;
use App\Http\Controllers\Admin\Utils\Platform\TicketController;
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
    // F1 help (DEC-094, W16b) — every signed-in user; articles follow their own `permissions`
    Route::get('help', [HelpController::class, 'index'])->name('utils.help.index');
    Route::get('help/pane', [HelpController::class, 'pane'])->name('utils.help.pane');
    Route::get('help/search', [HelpController::class, 'search'])->name('utils.help.search');
    Route::get('help/article/{key}', [HelpController::class, 'show'])->where('key', '[a-z0-9_\-/]+')->name('utils.help.show');

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
    Route::put('settings/{tab}/{section}', [SettingsAdminController::class, 'saveSection'])->name('utils.settings.section');   // DEC-091
    Route::post('settings/reset', [SettingsAdminController::class, 'reset'])->name('utils.settings.reset');
    Route::post('settings/image', [SettingsAdminController::class, 'image'])->name('utils.settings.image');   // DEC-083 image settings (branding.logo)

    // Tasks (DEC-062)
    Route::get('tasks', [TaskController::class, 'index'])->name('utils.tasks.index');
    Route::get('tasks/create', [TaskController::class, 'create'])->name('utils.tasks.create');
    Route::post('tasks', [TaskController::class, 'store'])->name('utils.tasks.store');
    Route::get('tasks/{id}', [TaskController::class, 'show'])->whereNumber('id')->name('utils.tasks.show');
    Route::get('tasks/{id}/edit', [TaskController::class, 'edit'])->whereNumber('id')->name('utils.tasks.edit');
    Route::put('tasks/{id}', [TaskController::class, 'update'])->whereNumber('id')->name('utils.tasks.update');
    Route::post('tasks/{id}/follow-up', [TaskController::class, 'followUp'])->whereNumber('id')->name('utils.tasks.follow-up');
    Route::delete('tasks/{id}', [TaskController::class, 'destroy'])->whereNumber('id')->name('utils.tasks.destroy');

    // Tickets (DEC-062)
    Route::get('tickets', [TicketController::class, 'index'])->name('utils.tickets.index');
    Route::get('tickets/create', [TicketController::class, 'create'])->name('utils.tickets.create');
    Route::get('tickets/report', [TicketController::class, 'report'])->name('utils.tickets.report');
    Route::post('tickets', [TicketController::class, 'store'])->name('utils.tickets.store');
    Route::get('tickets/{id}', [TicketController::class, 'show'])->whereNumber('id')->name('utils.tickets.show');
    Route::put('tickets/{id}', [TicketController::class, 'update'])->whereNumber('id')->name('utils.tickets.update');
    Route::post('tickets/{id}/transition', [TicketController::class, 'transition'])->whereNumber('id')->name('utils.tickets.transition');
    Route::post('tickets/{id}/remark', [TicketController::class, 'remark'])->whereNumber('id')->name('utils.tickets.remark');

    // Approvals (DEC-063)
    Route::get('approvals', [ApprovalController::class, 'index'])->name('utils.approvals.index');
    Route::get('approvals/create', [ApprovalController::class, 'create'])->name('utils.approvals.create');
    Route::post('approvals', [ApprovalController::class, 'store'])->name('utils.approvals.store');
    Route::get('approvals/report', [ApprovalReportController::class, 'index'])->name('utils.approvals.report');
    Route::get('approvals/report/export', [ApprovalReportController::class, 'export'])->name('utils.approvals.report.export');
    Route::get('approvals/{id}', [ApprovalController::class, 'show'])->whereNumber('id')->name('utils.approvals.show');
    Route::post('approvals/{id}/counter', [ApprovalController::class, 'counter'])->whereNumber('id')->name('utils.approvals.counter');
    Route::post('approvals/{id}/revise', [ApprovalController::class, 'revise'])->whereNumber('id')->name('utils.approvals.revise');
    Route::post('approvals/{id}/close', [ApprovalController::class, 'close'])->whereNumber('id')->name('utils.approvals.close');

    Route::get('approvals/admin/topics', [ApprovalAdminController::class, 'topics'])->name('utils.approvals.admin.topics');
    Route::post('approvals/admin/topics', [ApprovalAdminController::class, 'saveTopic'])->name('utils.approvals.admin.topics.save');
    Route::get('approvals/admin/rules', [ApprovalAdminController::class, 'rules'])->name('utils.approvals.admin.rules');
    Route::get('approvals/admin/rules/create', [ApprovalAdminController::class, 'editRule'])->name('utils.approvals.admin.rules.create');
    Route::post('approvals/admin/rules', [ApprovalAdminController::class, 'saveRule'])->name('utils.approvals.admin.rules.store');
    Route::get('approvals/admin/rules/{id}/edit', [ApprovalAdminController::class, 'editRule'])->whereNumber('id')->name('utils.approvals.admin.rules.edit');
    Route::put('approvals/admin/rules/{id}', [ApprovalAdminController::class, 'saveRule'])->whereNumber('id')->name('utils.approvals.admin.rules.update');
    Route::delete('approvals/admin/rules/{id}', [ApprovalAdminController::class, 'deleteRule'])->whereNumber('id')->name('utils.approvals.admin.rules.destroy');
    Route::get('approvals/admin/import', [ApprovalAdminController::class, 'importForm'])->name('utils.approvals.admin.import');
    Route::post('approvals/admin/import', [ApprovalAdminController::class, 'import'])->name('utils.approvals.admin.import.run');
    Route::get('approvals/admin/import/errors', [ApprovalAdminController::class, 'importErrors'])->name('utils.approvals.admin.import.errors');
    Route::get('approvals/admin/import/template', [ApprovalAdminController::class, 'template'])->name('utils.approvals.admin.import.template');
    Route::get('approvals/admin/simulate', [ApprovalAdminController::class, 'simulate'])->name('utils.approvals.admin.simulate');

    // Templates (DEC-064)
    Route::get('templates', [TemplateAdminController::class, 'index'])->name('utils.templates.index');
    Route::get('templates/create', [TemplateAdminController::class, 'edit'])->name('utils.templates.create');
    Route::get('templates/export', [TemplateAdminController::class, 'export'])->name('utils.templates.export');
    Route::post('templates/import', [TemplateAdminController::class, 'import'])->name('utils.templates.import');
    Route::post('templates', [TemplateAdminController::class, 'saveDraft'])->name('utils.templates.save');
    Route::get('templates/{id}', [TemplateAdminController::class, 'edit'])->whereNumber('id')->name('utils.templates.edit');
    Route::post('templates/versions/{versionId}/submit', [TemplateAdminController::class, 'submit'])->whereNumber('versionId')->name('utils.templates.submit');
    Route::post('templates/versions/{versionId}/approve', [TemplateAdminController::class, 'approve'])->whereNumber('versionId')->name('utils.templates.approve');
    Route::post('templates/versions/{versionId}/activate', [TemplateAdminController::class, 'activate'])->whereNumber('versionId')->name('utils.templates.activate');
    Route::post('templates/versions/{versionId}/preview', [TemplateAdminController::class, 'preview'])->whereNumber('versionId')->name('utils.templates.preview');

    // Comms operations (DEC-064)
    Route::get('comms/outbox', [CommsController::class, 'outbox'])->name('utils.comms.outbox');
    Route::get('comms/outbox/{id}', [CommsController::class, 'outboxShow'])->whereNumber('id')->name('utils.comms.outbox.show');
    Route::post('comms/outbox/{id}/resend', [CommsController::class, 'resend'])->whereNumber('id')->name('utils.comms.outbox.resend');
    Route::post('comms/email', [CommsController::class, 'sendEmail'])->name('utils.comms.email.send');
    Route::get('whatsapp', [CommsController::class, 'whatsapp'])->name('utils.whatsapp.index');
    Route::get('whatsapp/{threadId}', [CommsController::class, 'whatsapp'])->whereNumber('threadId')->name('utils.whatsapp.show');
    Route::post('whatsapp/{threadId}/send', [CommsController::class, 'whatsappSend'])->whereNumber('threadId')->name('utils.whatsapp.send');
    Route::post('whatsapp/{threadId}/manage', [CommsController::class, 'whatsappManage'])->whereNumber('threadId')->name('utils.whatsapp.manage');
    Route::get('calls', [CommsController::class, 'calls'])->name('utils.calls.index');
    Route::post('calls/dial', [CommsController::class, 'dial'])->name('utils.calls.dial');
    Route::post('calls/{callId}/dispose', [CommsController::class, 'dispose'])->whereNumber('callId')->name('utils.calls.dispose');
    Route::get('calls/{callId}/recording', [CommsController::class, 'recording'])->whereNumber('callId')->name('utils.calls.recording');
});

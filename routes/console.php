<?php

use App\Jobs\Platform\AutoCloseResolvedTickets;
use App\Jobs\Platform\FlagMissingCallRecordings;
use App\Jobs\Platform\FlagTicketSlaBreaches;
use App\Jobs\Platform\PurgeDeletedDocuments;
use App\Services\Platform\Settings\SettingsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Settings cache (FRS §1): warm on deploy, clear after a manual DB change.
Artisan::command('settings:cache', function (SettingsService $settings) {
    $settings->clearCache();
    $this->info('Cached '.$settings->warm().' settings.');
})->purpose('Clear and re-warm the settings cache');

Artisan::command('settings:clear', function (SettingsService $settings) {
    $settings->clearCache();
    $this->info('Settings cache cleared.');
})->purpose('Clear the settings cache');

// Platform utilities schedule (DEC-061+)
Schedule::job(new PurgeDeletedDocuments)->dailyAt('02:30')->name('docs-purge')->withoutOverlapping();
Schedule::job(new FlagTicketSlaBreaches)->hourly()->name('ticket-sla-breaches')->withoutOverlapping();
Schedule::job(new AutoCloseResolvedTickets)->dailyAt('03:00')->name('ticket-autoclose')->withoutOverlapping();
Schedule::job(new FlagMissingCallRecordings)->everyFifteenMinutes()->name('call-recordings-sweep')->withoutOverlapping();

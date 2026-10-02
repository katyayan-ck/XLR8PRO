<?php

namespace App\Jobs\Platform;

use App\Services\Platform\Help\HelpUsageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deletes help-usage events older than `help.usage_retention_days` (W16f, DEC-094). Scheduled daily in routes/console.php.
 */
class PurgeHelpUsage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function handle(HelpUsageService $usage): void
    {
        Log::info('[Help] purged usage events', ['count' => $usage->purge()]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('[Help] usage purge failed: '.$e->getMessage());
    }
}

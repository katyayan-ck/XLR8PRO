<?php

namespace App\Jobs\Platform;

use App\Services\Platform\Help\SupportRequestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Deletes support-request diagnostic zips older than `support.bundle_retention_days` (DEC-094, W16e; FRS §5.2). The
 * request and its ticket stay. Scheduled daily in routes/console.php.
 */
class PurgeSupportBundles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function handle(SupportRequestService $support): void
    {
        Log::info('[Support] purged diagnostic bundles', ['count' => $support->purge()]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('[Support] bundle purge failed: '.$e->getMessage());
    }
}

<?php

namespace App\Jobs\Platform;

use App\Services\Platform\Docs\DocsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Permanently removes documents soft-deleted more than `docs.purge_after_days` ago, with their
 * files (FRS DOC-09). Scheduled daily in routes/console.php.
 */
class PurgeDeletedDocuments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function handle(DocsService $docs): void
    {
        $days = (int) setting('docs.purge_after_days', 30);
        $purged = $docs->purge(max(1, $days));
        Log::info('[Docs] purged soft-deleted documents', ['count' => $purged, 'older_than_days' => $days]);
    }

    public function failed(Throwable $e): void
    {
        Log::error('[Docs] purge failed: '.$e->getMessage());
    }
}

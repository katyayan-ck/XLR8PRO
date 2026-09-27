<?php

namespace App\Jobs\Platform;

use App\Services\Platform\Comms\TelephonyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Every 15 minutes (FRS TEL-05): pulls recordings still missing after the grace period, flags the
 * rest and alerts telephony ops.
 */
class FlagMissingCallRecordings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function handle(TelephonyService $telephony): void
    {
        $flagged = $telephony->flagMissingRecordings();
        if ($flagged > 0) {
            Log::info('[Telephony] recordings missing', ['count' => $flagged]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('[Telephony] missing-recording sweep failed: '.$e->getMessage());
    }
}

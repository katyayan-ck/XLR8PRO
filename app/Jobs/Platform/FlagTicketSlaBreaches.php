<?php

namespace App\Jobs\Platform;

use App\Services\Platform\Ticket\TicketService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Hourly (FRS TCK-03): flags open tickets past their SLA once and alerts owner, assignees and desk.
 */
class FlagTicketSlaBreaches implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function handle(TicketService $tickets): void
    {
        $flagged = $tickets->flagBreaches();
        if ($flagged > 0) {
            Log::info('[Tickets] SLA breaches flagged', ['count' => $flagged]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('[Tickets] SLA breach job failed: '.$e->getMessage());
    }
}

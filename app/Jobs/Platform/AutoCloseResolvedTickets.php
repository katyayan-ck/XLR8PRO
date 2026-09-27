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
 * Daily (FRS TCK-06): closes RESOLVED tickets the requester did not answer within
 * `ticket.autoclose_days`, when `ticket.autoclose_enabled` is on.
 */
class AutoCloseResolvedTickets implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function handle(TicketService $tickets): void
    {
        $closed = $tickets->autoClose();
        if ($closed > 0) {
            Log::info('[Tickets] auto-closed resolved tickets', ['count' => $closed]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('[Tickets] auto-close job failed: '.$e->getMessage());
    }
}

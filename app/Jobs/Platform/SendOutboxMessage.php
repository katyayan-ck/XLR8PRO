<?php

namespace App\Jobs\Platform;

use App\Models\Comms\CommOutbox;
use App\Services\Platform\Comms\OutboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers one outbox row through its driver (FRS Part B law 2). Idempotent: a row that is no
 * longer QUEUED / RETRY is left alone. RETRY rows are released with backoff until
 * `comms.max_attempts`, then FAILED.
 */
class SendOutboxMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 60;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public int $outboxId) {}

    public function handle(OutboxService $outbox): void
    {
        $row = CommOutbox::query()->find($this->outboxId);
        if (! $row) {
            return;
        }
        $result = $outbox->deliver($row);
        if (! $result->ok && $result->code === 'RETRY') {
            $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)] ?? 60);
        }
    }

    public function failed(Throwable $e): void
    {
        CommOutbox::query()->whereKey($this->outboxId)->whereIn('status', ['QUEUED', 'RETRY'])
            ->update(['status' => 'FAILED', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        Log::error("[Comms] outbox #{$this->outboxId} failed: ".$e->getMessage());
    }
}

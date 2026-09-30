<?php

namespace App\Jobs\Platform;

use App\Models\User;
use App\Models\Utilities\Noty\Alert;
use App\Models\Utilities\Noty\Notification;
use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * FCM push for one inbox row (FRS NOT-03: push is always queued; NOT-04: payload carries type + id;
 * NOT-05: missing/stale tokens are recorded by FirebaseService, never retried forever).
 */
class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public string $table, public int $inboxId) {}

    public function handle(FirebaseService $firebase): void
    {
        $row = $this->table === 'alert' ? Alert::query()->find($this->inboxId) : Notification::query()->find($this->inboxId);
        $user = $row ? User::query()->find($row->user_id) : null;
        if (! $row || ! $user || $row->is_sent_via_fcm) {
            return;
        }
        // DEC-091: push switched off on Settings → Communication — the inbox row stays, nothing goes to the devices.
        if (! (bool) setting('comms.enabled.push', true)) {
            return;
        }

        $payload = (array) $row->payload;
        $result = $firebase->sendToUserDevices(
            $user,
            ['title' => $row->title, 'body' => strip_tags((string) $row->description), 'click_action' => $payload['deep_link'] ?? null],
            ['type' => (string) ($row->reference_type ?? 'SYSTEM'), 'id' => (string) ($row->reference_id ?? ''), 'kind' => (string) ($payload['kind'] ?? 'N')],
        );

        if (($result['success'] ?? 0) > 0) {
            $row->forceFill(['is_sent_via_fcm' => true, 'sent_at' => now()])->save();
        }
    }

    public function failed(Throwable $e): void
    {
        Log::warning('[Notify] push failed', ['table' => $this->table, 'id' => $this->inboxId, 'error' => $e->getMessage()]);
    }
}

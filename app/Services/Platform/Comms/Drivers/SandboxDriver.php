<?php

declare(strict_types=1);

namespace App\Services\Platform\Comms\Drivers;

use App\Models\Comms\CommOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sandbox driver (FRS Part B law 9): writes the message to comm_sandbox and the log instead of a
 * vendor, and reports it delivered. Used for SMS / WhatsApp today and as the EMAIL `log` driver.
 */
final class SandboxDriver implements ChannelDriver
{
    public function __construct(private readonly string $channel) {}

    public function name(): string
    {
        return $this->channel === 'EMAIL' ? 'log' : 'sandbox';
    }

    public function send(CommOutbox $outbox, array $payload): array
    {
        // attachments are listed, never copied into the sandbox
        $record = $payload;
        // OTP codes are never stored or logged, not even in the sandbox
        if (! empty($record['otp']) && isset($record['text'])) {
            $record['text'] = preg_replace('/\d{4,8}/', '••••••', (string) $record['text']);
        }
        if (isset($record['attachments'])) {
            $record['attachments'] = array_map(fn ($a) => array_diff_key((array) $a, ['content' => 1]), (array) $record['attachments']);
        }
        DB::table('xlr8_comm_sandbox')->insert([
            'outbox_id' => $outbox->id, 'channel' => $this->channel, 'driver' => $this->name(),
            'to_address' => $outbox->to_address, 'payload' => json_encode($record), 'created_at' => now(),
        ]);
        Log::channel(config('logging.default'))->info("[Comms sandbox] {$this->channel} #{$outbox->id} → {$outbox->to_address}");

        return ['ok' => true, 'provider_message_id' => 'sbx-'.Str::lower(Str::random(16)), 'delivered' => true, 'units' => $this->units($payload)];
    }

    public function supports(string $capability): bool
    {
        return false;
    }

    private function units(array $payload): ?int
    {
        if ($this->channel !== 'SMS') {
            return null;
        }
        $text = (string) ($payload['text'] ?? '');
        $unicode = preg_match('/[^\x00-\x7F]/', $text) === 1;
        $per = $unicode ? 70 : 160;

        return max(1, (int) ceil(mb_strlen($text) / $per));
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Platform\Comms\Drivers;

use App\Models\Comms\CommCall;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sandbox telephony: records the dial in comm_sandbox and returns a vendor call id. Call progress
 * and the recording arrive through the (unsigned-in-local) webhook, as with a real vendor; the
 * "recording" is a one-second silent WAV.
 */
final class SandboxTelephonyDriver implements TelephonyDriver
{
    public function name(): string
    {
        return 'sandbox';
    }

    public function dial(CommCall $call, string $agentNumber, string $customerNumber, array $options): array
    {
        $vendorId = 'sbx-call-'.Str::lower(Str::random(12));
        DB::table('xlr8_comm_sandbox')->insert([
            'outbox_id' => null, 'channel' => 'TELEPHONY', 'driver' => 'sandbox', 'to_address' => $this->mask($customerNumber),
            'payload' => json_encode(['call_id' => $call->id, 'vendor_call_id' => $vendorId, 'agent' => $this->mask($agentNumber), 'options' => $options]),
            'created_at' => now(),
        ]);

        return ['ok' => true, 'vendor_call_id' => $vendorId];
    }

    public function fetchRecording(CommCall $call): ?string
    {
        $rate = 8000;
        $samples = str_repeat("\x80", $rate);

        return 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate, 1, 8).'data'.pack('V', strlen($samples)).$samples;
    }

    public function supports(string $capability): bool
    {
        return false;
    }

    private function mask(string $number): string
    {
        return substr($number, 0, 5).str_repeat('X', max(0, strlen($number) - 7)).substr($number, -2);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Platform\Comms\Drivers;

use App\Models\Comms\CommCall;

/** A CPaaS / PBX behind the telephony wrapper (FRS §16). */
interface TelephonyDriver
{
    public function name(): string;

    /**
     * Ring the agent, then the customer.
     *
     * @param  array<string, mixed>  $options  caller_id, record
     * @return array{ok: bool, vendor_call_id?: ?string, error?: ?string}
     */
    public function dial(CommCall $call, string $agentNumber, string $customerNumber, array $options): array;

    /** Recording bytes for a vendor call id, or null when not ready. */
    public function fetchRecording(CommCall $call): ?string;

    /** listen / whisper / barge — NOT_SUPPORTED must not break dialling (TEL-10). */
    public function supports(string $capability): bool;
}

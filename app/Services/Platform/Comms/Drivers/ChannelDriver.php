<?php

declare(strict_types=1);

namespace App\Services\Platform\Comms\Drivers;

use App\Models\Comms\CommOutbox;

/**
 * A vendor behind EMAIL / SMS / WHATSAPP (FRS Part B law 1). Only driver classes may talk to a
 * vendor; feature code never imports a vendor SDK.
 */
interface ChannelDriver
{
    public function name(): string;

    /**
     * Deliver one outbox row. `$payload` is the rendered, channel-specific message.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, provider_message_id?: ?string, delivered?: bool, units?: ?int, error?: ?string, retryable?: bool}
     */
    public function send(CommOutbox $outbox, array $payload): array;

    /** Optional capabilities, e.g. 'poll', 'interactive', 'group'. */
    public function supports(string $capability): bool;
}

<?php

declare(strict_types=1);

namespace App\Events\Platform;

use Illuminate\Foundation\Events\Dispatchable;

/** Fired after a driver accepted an outbox row (FRS §10 events). */
final class OutboxAccepted
{
    use Dispatchable;

    public function __construct(
        public readonly int $outboxId,
        public readonly string $channel,
        public readonly string $status,
    ) {}
}

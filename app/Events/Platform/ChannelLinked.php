<?php

declare(strict_types=1);

namespace App\Events\Platform;

use Illuminate\Foundation\Events\Dispatchable;

/** Fired after a WhatsApp thread is linked to a record (FRS WA-12). */
final class ChannelLinked
{
    use Dispatchable;

    public function __construct(
        public readonly int $threadId,
        public readonly string $refType,
        public readonly int $refId,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Events\Platform;

use Illuminate\Foundation\Events\Dispatchable;

final class ChatEntryAdded
{
    use Dispatchable;

    public function __construct(
        public readonly int $masterId,
        public readonly int $threadId,
        public readonly string $kind,
        public readonly string $action,
    ) {}
}

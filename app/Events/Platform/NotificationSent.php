<?php

declare(strict_types=1);

namespace App\Events\Platform;

use Illuminate\Foundation\Events\Dispatchable;

final class NotificationSent
{
    use Dispatchable;

    /** @param  list<int>  $recipients */
    public function __construct(
        public readonly int $dispatchId,
        public readonly string $kind,
        public readonly ?string $refType,
        public readonly ?int $refId,
        public readonly array $recipients,
    ) {}
}

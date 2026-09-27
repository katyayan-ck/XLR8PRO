<?php

declare(strict_types=1);

namespace App\Events\Platform;

use Illuminate\Foundation\Events\Dispatchable;

/** Fired after every successful approval write (FRS APR-09). $change is the event type (OPENED, COUNTERED…). */
final class ApprovalChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $requestId,
        public readonly string $change,
        public readonly ?int $actorId,
    ) {}
}

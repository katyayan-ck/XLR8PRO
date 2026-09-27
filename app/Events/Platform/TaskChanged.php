<?php

declare(strict_types=1);

namespace App\Events\Platform;

use Illuminate\Foundation\Events\Dispatchable;

/** Fired after every successful task write (FRS availability law 5). $change is the action or new status. */
final class TaskChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $taskId,
        public readonly string $change,
        public readonly ?int $actorId,
    ) {}
}

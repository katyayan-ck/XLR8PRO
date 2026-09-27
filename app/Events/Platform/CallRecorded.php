<?php

declare(strict_types=1);

namespace App\Events\Platform;

use Illuminate\Foundation\Events\Dispatchable;

/** Fired after a call recording is stored in Docs (FRS §10 events). */
final class CallRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly int $callId,
        public readonly int $docId,
        public readonly ?string $refType,
    ) {}
}

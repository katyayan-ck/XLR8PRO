<?php

declare(strict_types=1);

namespace App\Events\Platform;

use Illuminate\Foundation\Events\Dispatchable;

/** A setting changed (FRS SET-08). Encrypted values are never carried. */
final class SettingsChanged
{
    use Dispatchable;

    public function __construct(
        public readonly string $key,
        public readonly mixed $old,
        public readonly mixed $new,
        public readonly ?string $scopeType = null,
        public readonly ?string $scopeCode = null,
        public readonly ?int $actorId = null,
    ) {}
}

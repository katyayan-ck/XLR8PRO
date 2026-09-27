<?php

declare(strict_types=1);

namespace App\Services\IAM\DataScope;

/**
 * A user's effective data scope (DEC-071): for every scope level, the codes the user may see, or null when the
 * level is unrestricted. Built by ScopeResolver from the user's scope rows and the master hierarchies.
 *
 *   allowed('segment')  → ['PV']            the user holds PV
 *   allowed('model')    → ['THAR']          restricted to Thar inside PV
 *   allowed('variant')  → ['THAR-LX', …]    every Thar variant (no variant rows = all children)
 *   allowed('branch')   → null              no branch rows = every branch
 */
final class ScopeSet
{
    /** @param  array<string, list<string>|null>  $levels  level => codes | null (unrestricted) */
    public function __construct(private readonly array $levels = [], private readonly bool $bypass = false) {}

    /** Superadmin, bypass_data_scoping, scoping switched off or no user: sees everything. */
    public static function unrestricted(): self
    {
        return new self([], true);
    }

    /** @return list<string>|null codes allowed at this level; null = no restriction */
    public function allowed(string $level): ?array
    {
        return $this->bypass ? null : ($this->levels[$level] ?? null);
    }

    public function isUnrestricted(): bool
    {
        if ($this->bypass) {
            return true;
        }
        foreach ($this->levels as $codes) {
            if ($codes !== null) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, list<string>|null> */
    public function toArray(): array
    {
        return $this->bypass ? [] : $this->levels;
    }

    /** Stable key for per-scope caches (menu counts etc.): same scope → same hash. */
    public function hash(): string
    {
        if ($this->isUnrestricted()) {
            return 'all';
        }
        $levels = array_filter($this->levels, fn ($codes) => $codes !== null);
        ksort($levels);

        return substr(sha1((string) json_encode($levels)), 0, 16);
    }
}

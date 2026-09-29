<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing;

use App\Services\Platform\Settings\SettingsService;

/**
 * The "pricing last updated" stamp the mobile app compares to decide whether its offline vehicle / pricing / accessory
 * data must re-sync (DEC-083). Setting `pricing.last_updated_at` (ISO-8601), served by `GET v1/settings/category/pricing`.
 *
 * touch() only flags the change; the setting is written once — when the request terminates, after each queued job
 * (AppServiceProvider), or on an explicit flush() — so a 2,500-vehicle publish writes it once, not per row.
 *
 *   app(PricingSyncStamp::class)->touch();          // something price-relevant changed
 *   app(PricingSyncStamp::class)->lastUpdated();    // '2026-09-29T14:05:11+05:30' | null
 */
class PricingSyncStamp
{
    public const KEY = 'pricing.last_updated_at';

    private bool $pending = false;

    private bool $hooked = false;

    public function touch(): void
    {
        $this->pending = true;
        if (! $this->hooked) {
            $this->hooked = true;
            app()->terminating(fn () => $this->flush());
        }
    }

    public function flush(): void
    {
        if (! $this->pending) {
            return;
        }
        $this->pending = false;
        app(SettingsService::class)->set(self::KEY, now()->toIso8601String());
    }

    public function isPending(): bool
    {
        return $this->pending;
    }

    public function lastUpdated(): ?string
    {
        $value = setting(self::KEY);

        return $value ? (string) $value : null;
    }
}

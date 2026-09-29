<?php

declare(strict_types=1);

namespace App\Support\PricingMaster;

use App\Support\PricingMaster\Masters\CorporateMaster;
use App\Support\PricingMaster\Masters\DealerChargesMaster;
use App\Support\PricingMaster\Masters\DiscountBreakupMaster;
use App\Support\PricingMaster\Masters\ExchangeMaster;
use App\Support\PricingMaster\Masters\LoyaltyMaster;
use App\Support\PricingMaster\Masters\RsaMaster;
use App\Support\PricingMaster\Masters\ShieldMaster;

/**
 * Every pricing master screen under Admin → Pricing (DEC-083), in menu order. Route key → definition.
 *
 *   MasterRegistry::get('rsa')->label();   // 'RSA'
 */
final class MasterRegistry
{
    /** @var list<class-string<MasterDefinition>> */
    public const MASTERS = [
        DealerChargesMaster::class,
        DiscountBreakupMaster::class,
        RsaMaster::class,
        ShieldMaster::class,
        CorporateMaster::class,
        ExchangeMaster::class,
        LoyaltyMaster::class,
    ];

    /** @return array<string, MasterDefinition> key => definition */
    public static function all(): array
    {
        $out = [];
        foreach (self::MASTERS as $class) {
            $definition = app($class);
            $out[$definition->key()] = $definition;
        }

        return $out;
    }

    public static function get(string $key): MasterDefinition
    {
        return self::all()[$key] ?? throw new \InvalidArgumentException("Unknown pricing master {$key}.");
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }
}

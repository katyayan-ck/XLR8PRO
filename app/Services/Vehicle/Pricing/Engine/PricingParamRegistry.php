<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Engine;

use App\Models\Vehicle\Accessory;
use App\Models\Vehicle\AccessoryScope;
use App\Models\Vehicle\Pricing\Addon;
use App\Models\Vehicle\Pricing\DealerCharge;
use App\Models\Vehicle\Pricing\Discount;
use App\Models\Vehicle\Pricing\InsAddonRate;
use App\Models\Vehicle\Pricing\InsBaseRule;
use App\Models\Vehicle\Pricing\InsDefault;
use App\Models\Vehicle\Pricing\InsIdvSlot;
use App\Models\Vehicle\Pricing\PermitMap;
use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Pricing\RtoRule;
use App\Models\Vehicle\Pricing\TcsConfig;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;

/** The models PricingParamObserver watches (DEC-083). New pricing masters add themselves to PRICING_PARAMS. */
final class PricingParamRegistry
{
    public const PRICING_PARAMS = [
        Pricing::class, Addon::class, Discount::class, DealerCharge::class,
        RtoRule::class, InsBaseRule::class, InsIdvSlot::class, InsAddonRate::class, InsDefault::class,
        PermitMap::class, TcsConfig::class,
    ];

    public const VEHICLE_MASTERS = [Segment::class, SubSegment::class, VehicleModel::class, Variant::class];

    public const ACCESSORIES = [Accessory::class, AccessoryScope::class];

    /** @return list<class-string> */
    public static function all(): array
    {
        return array_merge(self::PRICING_PARAMS, self::VEHICLE_MASTERS, self::ACCESSORIES);
    }
}

<?php

declare(strict_types=1);

namespace App\Support\PricingMaster\Masters;

use App\Models\Vehicle\Pricing\Addon;
use App\Services\Vehicle\Pricing\Addons\AddonService;
use App\Support\Entity\EntityService;
use App\Support\PricingMaster\WorkbookGroupMaster;

/** RSA / Shield rows of xlr8_vehicle_pricing_addons (DEC-083). */
abstract class AddonMaster extends WorkbookGroupMaster
{
    public function model(): string
    {
        return Addon::class;
    }

    public function service(): EntityService
    {
        return app(AddonService::class);
    }

    public function defaults(): array
    {
        return ['addon_type' => $this->group()];
    }
}

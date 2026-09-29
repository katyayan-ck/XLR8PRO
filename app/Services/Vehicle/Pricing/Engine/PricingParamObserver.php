<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Engine;

use App\Models\Vehicle\Accessory;
use App\Models\Vehicle\AccessoryScope;
use App\Services\Vehicle\Pricing\PricingSyncStamp;
use App\Services\Vehicle\Pricing\Session\PricingChangeRecorder;
use Illuminate\Database\Eloquent\Model;

/**
 * DEC-083 on every price-relevant model (registered in AppServiceProvider):
 *  - vehicle masters and accessories bump the app's pricing sync stamp;
 *  - pricing parameters and vehicle masters changed outside a Pricing Process queue an automatic recalculation of the
 *    affected vehicles (accessories never do; inside a process step 9 recalculates everything).
 */
class PricingParamObserver
{
    private const STAMP_ONLY = [Accessory::class, AccessoryScope::class];

    public function __construct(
        private readonly PricingRecalcService $recalc,
        private readonly PricingChangeRecorder $recorder,
        private readonly PricingSyncStamp $stamp,
    ) {}

    public function saved(Model $model): void
    {
        if ($model->wasRecentlyCreated || $model->wasChanged()) {   // a save without changes still fires saved
            $this->changed($model);
        }
    }

    public function deleted(Model $model): void
    {
        $this->changed($model);
    }

    public function restored(Model $model): void
    {
        $this->changed($model);
    }

    private function changed(Model $model): void
    {
        if (in_array($model::class, self::STAMP_ONLY, true)) {
            $this->stamp->touch();

            return;
        }
        if (in_array($model::class, PricingParamRegistry::VEHICLE_MASTERS, true)) {
            $this->stamp->touch();
        }
        if ($this->recorder->active() === null) {
            $this->recalc->mark($model);
        }
    }
}

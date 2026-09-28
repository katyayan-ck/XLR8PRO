<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Session;

use Illuminate\Database\Eloquent\Model;

/**
 * Feeds PricingChangeRecorder from model events on the pricing and vehicle master models (DEC-073). Does nothing
 * unless a pricing session is recording (PricingChangeRecorder::within()). Registered in AppServiceProvider.
 */
class PricingChangeObserver
{
    public function __construct(private readonly PricingChangeRecorder $recorder) {}

    public function created(Model $model): void
    {
        $this->recorder->created($model);
    }

    public function updated(Model $model): void
    {
        $this->recorder->updated($model);
    }

    public function deleted(Model $model): void
    {
        if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
            $this->recorder->softDeleted($model);
        }
    }
}

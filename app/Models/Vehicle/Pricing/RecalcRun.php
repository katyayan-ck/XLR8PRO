<?php

namespace App\Models\Vehicle\Pricing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One automatic pricing recalculation run (DEC-083), written by PricingRecalcService.
 *
 * @property int $id
 * @property string $status running | done | failed
 * @property list<string>|null $reasons
 * @property int $vehicles
 * @property int $published
 * @property int $failed
 * @property int $skipped
 * @property int $snapshots
 * @property list<array{code: string, message: string}>|null $failures
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class RecalcRun extends Model
{
    protected $table = 'xlr8_vehicle_pricing_recalc_runs';

    protected $fillable = ['status', 'reasons', 'vehicles', 'published', 'failed', 'skipped', 'snapshots', 'failures', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['reasons' => 'array', 'failures' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}

<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use App\Services\Vehicle\Pricing\Addons\DealerChargeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $import_session_id
 * @property string $segment
 * @property string|null $permit
 * @property string|null $model_code
 * @property string|null $charge_code
 * @property string|null $charge_name
 * @property string|null $amount
 * @property string $incidental
 * @property string $fastag
 * @property string $trc
 * @property string $rto_tape
 * @property string $cod
 * @property string $kazam
 * @property Carbon|null $wef_date
 * @property Carbon|null $expired_on
 * @property bool $is_active
 */
class DealerCharge extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_vehicle_pricing_dealer_charges';

    /** Columns = the entity service's fields (DEC-050/057); the service owns their rules. */
    protected string $entityService = DealerChargeService::class;

    protected $fillable = [
        'import_session_id',
        'segment',
        'permit',
        'model_code',
        'charge_code',
        'charge_name',
        'amount',
        'incidental',
        'fastag',
        'trc',
        'rto_tape',
        'cod',
        'kazam',
        'extra_json',
        'is_active',
        'wef_date',
        'expired_on',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'amount' => 'decimal:2',
            'incidental' => 'decimal:2',
            'fastag' => 'decimal:2',
            'trc' => 'decimal:2',
            'rto_tape' => 'decimal:2',
            'cod' => 'decimal:2',
            'kazam' => 'decimal:2',
            'extra_json' => 'array',
            'is_active' => 'boolean',
            'wef_date' => 'date',
            'expired_on' => 'date',
        ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

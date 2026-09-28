<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use App\Services\Vehicle\Pricing\Rules\RtoRuleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $import_session_id
 * @property string|null $code
 * @property string $permit
 * @property int|null $wheels
 * @property string|null $reg_type
 * @property string|null $body_type
 * @property string|null $gvw_range
 * @property string|null $seater
 * @property string|null $fuel_type
 * @property string|null $cc_range
 * @property string|null $assessable_range
 * @property string $tax_factor
 * @property string|null $tax_basis
 * @property string|null $tax_slab
 * @property string $surcharge
 * @property string|null $surcharge_formula
 * @property string $hypothecation
 * @property string $green_tax
 * @property string $registration_fee
 * @property string $duplicate_tax_card
 * @property string $fitness
 * @property string $penalty
 * @property string $rto_tape
 * @property array<string, mixed>|null $extra_json
 * @property Carbon|null $wef_date
 * @property Carbon|null $expired_on
 * @property bool $is_active
 */
class RtoRule extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_rto_rules';

    /** Columns = the entity service's fields (DEC-050/056); the service owns their rules. */
    protected string $entityService = RtoRuleService::class;

    protected $fillable = [
        'import_session_id',
        'code',
        'permit',
        'wheels',
        'reg_type',
        'body_type',
        'gvw_range',
        'seater',
        'fuel_type',
        'cc_range',
        'assessable_range',
        'tax_factor',
        'tax_basis',
        'tax_slab',
        'surcharge',
        'surcharge_formula',
        'hypothecation',
        'green_tax',
        'registration_fee',
        'duplicate_tax_card',
        'fitness',
        'penalty',
        'rto_tape',
        'is_active',
        'wef_date',
        'expired_on',
        'extra_json',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'wheels' => 'integer',
            'tax_factor' => 'decimal:6',
            'surcharge' => 'decimal:2',
            'hypothecation' => 'decimal:2',
            'green_tax' => 'decimal:2',
            'registration_fee' => 'decimal:2',
            'duplicate_tax_card' => 'decimal:2',
            'fitness' => 'decimal:2',
            'penalty' => 'decimal:2',
            'rto_tape' => 'decimal:2',
            'is_active' => 'boolean',
            'wef_date' => 'date',
            'expired_on' => 'date',
            'extra_json' => 'array',
        ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expired_on')
                    ->orWhere('expired_on', '>=', now()->toDateString());
            })
            ->where(function ($q) {
                $q->whereNull('wef_date')
                    ->orWhere('wef_date', '<=', now()->toDateString());
            });
    }

    public static function findBestMatch(array $criteria): ?self
    {
        $query = self::query()
            ->active()
            ->where('permit', $criteria['permit'] ?? null);

        foreach (['wheels', 'fuel_type', 'gvw_range', 'seater', 'cc_range', 'reg_type', 'body_type'] as $field) {
            if (! empty($criteria[$field])) {
                $query->where(function ($q) use ($field, $criteria) {
                    $q->where($field, $criteria[$field])->orWhereNull($field);
                });
            }
        }

        return $query->orderByRaw('
            (CASE WHEN wheels IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN fuel_type IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN gvw_range IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN seater IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN cc_range IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN body_type IS NOT NULL THEN 1 ELSE 0 END) DESC
        ')->first();
    }
}

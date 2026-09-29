<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use App\Services\Vehicle\Pricing\Rules\InsDefaultService;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int $id
 * @property int|null $import_session_id
 * @property string|null $segment DEC-083: segment + permit preference (model_code ANY); a model row overrides it
 * @property string $model_code
 * @property string|null $permit
 * @property string $insurance_company
 * @property int $priority
 * @property bool $is_default
 * @property bool $is_active
 */
class InsDefault extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_ins_defaults';

    /** Columns = the entity service's fields (DEC-050/056); the service owns their rules. */
    protected string $entityService = InsDefaultService::class;

    protected $fillable = [
        'import_session_id',
        'segment',
        'model_code',
        'permit',
        'insurance_company',
        'priority',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_active' => 'boolean',
            'wef_date' => 'date',
        ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);   // the table has no WEF (BUG-202)
    }

    /**
     * Ordered list of company codes for a model + permit ([] when none is set).
     *
     * @return list<string>
     */
    public static function getCompanies(string $modelCode, string $permit = 'Private'): array
    {
        // the model's own rows win over ANY; within them, by priority (Co. 1 = the default) — BUG-202
        $rows = self::query()->active()
            ->whereIn('model_code', [strtoupper($modelCode), 'ANY'])
            ->where(fn ($q) => $q->where('permit', $permit)->orWhereNull('permit'))
            ->orderByRaw("CASE WHEN model_code = 'ANY' THEN 1 ELSE 0 END")
            ->orderBy('priority')
            ->get(['model_code', 'insurance_company']);
        if ($rows->isEmpty()) {
            return [];
        }
        $own = $rows->where('model_code', '!=', 'ANY');

        return ($own->isNotEmpty() ? $own : $rows)->pluck('insurance_company')->unique()->values()->all();
    }
}

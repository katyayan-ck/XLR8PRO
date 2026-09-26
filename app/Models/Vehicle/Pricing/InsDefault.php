<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use App\Services\Vehicle\Pricing\Rules\InsDefaultService;
use Illuminate\Database\Eloquent\Builder;

class InsDefault extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_ins_defaults';

    /** Columns = the entity service's fields (DEC-050/056); the service owns their rules. */
    protected string $entityService = InsDefaultService::class;

    protected $fillable = [
        'import_session_id',
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
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('wef_date')
                    ->orWhere('wef_date', '<=', now()->toDateString());
            });
    }

    /**
     * Ordered list of company codes for a model + permit
     */
    public static function getCompanies(string $modelCode, string $permit = 'Private'): array
    {
        $row = self::query()
            ->active()
            ->where('model_code', $modelCode)
            ->where('permit', $permit)
            ->orderByDesc('wef_date')
            ->first();

        if (! $row) {
            return ['USGI'];
        }

        return array_values(array_filter([
            $row->default_company,
            $row->company_priority_2,
            $row->company_priority_3,
        ]));
    }
}

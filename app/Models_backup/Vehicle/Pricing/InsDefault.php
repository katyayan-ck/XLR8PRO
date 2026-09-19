<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class InsDefault extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_ins_defaults';

    protected $fillable = [
        'model_code',
        'permit',
        'default_company',
        'company_priority_2',
        'company_priority_3',
        'is_active',
        'wef_date',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_active' => 'boolean',
            'wef_date'  => 'date',
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

        if (!$row) {
            return ['USGI'];
        }

        return array_values(array_filter([
            $row->default_company,
            $row->company_priority_2,
            $row->company_priority_3,
        ]));
    }
}
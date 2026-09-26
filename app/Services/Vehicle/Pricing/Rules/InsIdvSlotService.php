<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Rules;

use App\Models\Vehicle\Pricing\InsIdvSlot;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Validation\Rule;

/**
 * IDV slots of an insurance base rule (xlr8_vehicle_pricing_ins_idv_slots) — their only write
 * path (DEC-050/056). `idv_basis` keeps the sheet's formula text ("95% of Invoice"); `idv_pct`
 * is its percentage.
 *
 * @extends EntityService<InsIdvSlot>
 */
final class InsIdvSlotService extends EntityService
{
    protected function model(): string
    {
        return InsIdvSlot::class;
    }

    public function fields(): array
    {
        return [
            Field::make('base_rule_id')->rules('integer', Rule::exists('xlr8_vehicle_pricing_ins_base_rules', 'id'))->required(),
            Field::integer('year_no', 1)->label('Year')->rules('max:255')->required(),
            Field::text('idv_basis', 60)->label('IDV Basis'),
            Field::percent('idv_pct')->label('IDV %')->rules('max:999.999'),
        ];
    }

    protected function derive(array $data, array $input): array
    {
        if (($data['idv_pct'] ?? null) === null && ($data['idv_basis'] ?? null) !== null) {
            $pct = $this->normaliseField('idv_pct', $data['idv_basis']);
            $data['idv_pct'] = is_numeric($pct) ? $pct : null;
        }

        return $data;
    }
}

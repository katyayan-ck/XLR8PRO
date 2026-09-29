<?php

declare(strict_types=1);

namespace App\Support\PricingMaster\Masters;

use App\Models\Vehicle\Pricing\InsCompany;
use App\Models\Vehicle\Pricing\InsDefault;
use App\Services\Vehicle\Pricing\Rules\InsDefaultService;
use App\Support\Entity\EntityService;
use App\Support\PricingMaster\MasterDefinition;
use Illuminate\Database\Eloquent\Builder;

/**
 * Insurance company preferences (DEC-083): per segment + permit (model ANY) or a model override, one row per company;
 * priority 1 is the default company. The engine uses the model's rows, else the segment's, else the ANY rows.
 *
 * @extends MasterDefinition<InsDefault>
 */
final class InsPreferencesMaster extends MasterDefinition
{
    public function key(): string
    {
        return 'insurance-preferences';
    }

    public function label(): string
    {
        return 'Insurance Preferences';
    }

    public function permission(): string
    {
        return 'PRC_INPF';
    }

    public function icon(): string
    {
        return 'la-sort-amount-down';
    }

    public function description(): string
    {
        return 'Company order per segment + permit; a row with a model overrides the segment. Priority 1 is the default company.';
    }

    public function model(): string
    {
        return InsDefault::class;
    }

    public function service(): EntityService
    {
        return app(InsDefaultService::class);
    }

    public function formFields(): array
    {
        return ['segment', 'permit', 'model_code', 'insurance_company', 'priority', 'is_default'];
    }

    public function input(string $name): array
    {
        $spec = parent::input($name);
        if ($name === 'insurance_company') {
            $spec['type'] = 'select';
            $spec['options'] = InsCompany::query()->where('is_active', true)->orderBy('sort_order')->pluck('code')->all();
            $spec['required'] = true;
        }

        return $spec;
    }

    /** @return Builder<InsDefault> */
    public function query(bool $history = false): Builder
    {
        return InsDefault::query()->when(! $history, fn ($q) => $q->where('is_active', true))->orderBy('segment')->orderBy('permit')->orderBy('model_code')->orderBy('priority');
    }

    public function columns(): array
    {
        return [
            ['field' => 'segment', 'label' => 'Segment', 'pinned' => 'left', 'width' => 110],
            ['field' => 'permit', 'label' => 'Permit', 'width' => 120],
            ['field' => 'model_code', 'label' => 'Model', 'width' => 150],
            ['field' => 'insurance_company', 'label' => 'Insurance Co', 'width' => 150],
            ['field' => 'priority', 'label' => 'Priority', 'type' => 'number', 'width' => 100],
            ['field' => 'is_default', 'label' => 'Default', 'type' => 'bool', 'width' => 100],
        ];
    }
}

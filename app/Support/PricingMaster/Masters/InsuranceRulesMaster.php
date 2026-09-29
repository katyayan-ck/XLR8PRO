<?php

declare(strict_types=1);

namespace App\Support\PricingMaster\Masters;

use App\Models\Vehicle\Pricing\InsAddon;
use App\Models\Vehicle\Pricing\InsAddonRate;
use App\Models\Vehicle\Pricing\InsBaseRule;
use App\Models\Vehicle\Pricing\InsCompany;
use App\Services\Vehicle\Pricing\Import\InsuranceWorkbookService;
use App\Services\Vehicle\Pricing\Rules\InsAddonRateService;
use App\Services\Vehicle\Pricing\Rules\InsBaseRuleService;
use App\Services\Vehicle\Pricing\Rules\InsIdvSlotService;
use App\Services\Vehicle\Pricing\Rules\RuleFormula;
use App\Support\Entity\EntityService;
use App\Support\PricingMaster\MasterDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Insurance Rules (DEC-083): one premium row per company × plan × scope — the OD / TP heads (numbers or formulas such as
 * "1117 x (Seat -1)"), the IDV slots (e.g. "95% of Invoice") and a rate per add-on of the add-on master. The form edits
 * all of it; import / export = the "Insu Premium" sheet of the Insurance workbook (replaces the premiums at the WEF).
 *
 * @extends MasterDefinition<InsBaseRule>
 */
final class InsuranceRulesMaster extends MasterDefinition
{
    private const SCOPE = ['company', 'plan', 'permit', 'wheels', 'fuel_type', 'cc_range', 'gvw_range', 'seating'];

    public function key(): string
    {
        return 'insurance-rules';
    }

    public function label(): string
    {
        return 'Insurance Rules';
    }

    public function permission(): string
    {
        return 'PRC_INSR';
    }

    public function icon(): string
    {
        return 'la-shield-alt';
    }

    public function description(): string
    {
        return 'OD / TP heads, IDV slots and add-on rates per company × plan × permit, wheels, fuel, CC, GVW and seating. Heads and rates may be formulas (OD, IDV, SEAT, LPG).';
    }

    public function model(): string
    {
        return InsBaseRule::class;
    }

    public function service(): EntityService
    {
        return app(InsBaseRuleService::class);
    }

    public function importReplacesAll(): bool
    {
        return true;
    }

    public function formFields(): array
    {
        return array_merge(self::SCOPE, array_keys($this->headFields()), ['idv_1', 'idv_2', 'idv_3'], array_keys($this->addonFields()), ['wef_date']);
    }

    public function input(string $name): array
    {
        if ($name === 'company') {
            return ['type' => 'select', 'label' => 'Insurance Co', 'options' => InsCompany::query()->where('is_active', true)->orderBy('sort_order')->pluck('code')->all(), 'required' => true, 'help' => ''];
        }
        $virtual = $this->headFields() + $this->addonFields() + ['idv_1' => 'IDV Year 1', 'idv_2' => 'IDV Year 2', 'idv_3' => 'IDV Year 3'];
        if (isset($virtual[$name])) {
            return ['type' => 'text', 'label' => $virtual[$name], 'options' => [], 'required' => false,
                'help' => str_starts_with($name, 'idv_') ? 'e.g. 95% of Invoice' : 'A number, or a formula with OD / IDV / SEAT / LPG.'];
        }

        return parent::input($name);
    }

    /** @return Builder<InsBaseRule> */
    public function query(bool $history = false): Builder
    {
        return InsBaseRule::query()->with('idvSlots')->when(! $history, fn ($q) => $q->where('is_active', true));
    }

    public function columns(): array
    {
        $columns = [
            ['field' => 'company', 'label' => 'Insurance Co', 'pinned' => 'left', 'width' => 120],
            ['field' => 'plan', 'label' => 'Plan', 'width' => 80],
            ['field' => 'permit', 'label' => 'Permit', 'width' => 110],
            ['field' => 'wheels', 'label' => 'Wheels', 'width' => 90],
            ['field' => 'fuel_type', 'label' => 'Fuel', 'width' => 90],
            ['field' => 'cc_range', 'label' => 'CC', 'width' => 110],
            ['field' => 'gvw_range', 'label' => 'GVW', 'width' => 110],
            ['field' => 'seating', 'label' => 'Seating', 'width' => 100],
            ['field' => 'idv_1', 'label' => 'IDV 1', 'width' => 140],
        ];
        foreach (['head_od_factor', 'head_tp_basic', 'head_tp_per_pass', 'head_tp_pa_owner'] as $head) {
            $columns[] = ['field' => $head, 'label' => $this->headFields()[$head], 'width' => 150];
        }
        foreach (array_slice($this->addonFields(), 0, 4, true) as $field => $label) {
            $columns[] = ['field' => $field, 'label' => $label, 'width' => 150];
        }
        $columns[] = ['field' => 'wef_date', 'label' => 'WEF', 'type' => 'date', 'width' => 120];

        return $columns;
    }

    public function row(Model $model): array
    {
        $row = ['id' => $model->id];
        foreach (self::SCOPE as $field) {
            $row[$field] = $model->getAttribute($field);
        }
        $row['wef_date'] = $model->wef_date?->format('Y-m-d');
        foreach (array_keys(InsuranceWorkbookService::HEADS) as $head) {
            $row['head_'.$head] = $model->heads[$head] ?? null;
        }
        foreach ($model->idvSlots as $slot) {
            $row['idv_'.$slot->year_no] = $slot->idv_basis;
        }
        $rates = InsAddonRate::query()->where('base_rule_id', $model->id)->where('is_active', true)->get()->keyBy(fn ($r) => strtoupper($r->addon_slug));
        foreach (array_keys($this->addonFields()) as $field) {
            $rate = $rates->get(substr($field, 6));
            $row[$field] = $rate ? ($rate->rate_text ?? $rate->rate_value) : null;
        }

        return $row;
    }

    public function save(array $input, ?Model $existing = null): Model
    {
        $base = array_intersect_key($input, array_flip(array_merge(self::SCOPE, ['wef_date'])));
        $heads = [];
        foreach ($this->headFields() as $field => $label) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($value !== '') {
                $heads[substr($field, 5)] = $this->checked($value, $label);
            }
        }
        $base['heads'] = $heads;
        foreach (InsuranceWorkbookService::HEAD_COLUMNS as $head => $column) {
            $base[$column] = isset($heads[$head]) && is_float($heads[$head]) ? $heads[$head] : null;
        }

        return DB::transaction(function () use ($base, $input, $existing) {
            $rule = parent::save($base, $existing);
            $versioned = $existing && $existing->getKey() !== $rule->getKey();
            $wef = $rule->wef_date?->format('Y-m-d') ?? now()->toDateString();
            if ($existing) {   // the old rule's rates stop; slots of an in-place edit are replaced
                app(InsAddonRateService::class)->expireActive($wef, ['base_rule_id' => $existing->getKey()]);
                if (! $versioned) {
                    $rule->idvSlots()->get()->each->delete();
                }
            }
            foreach ([1, 2, 3] as $year) {
                if (($basis = trim((string) ($input['idv_'.$year] ?? ''))) !== '') {
                    app(InsIdvSlotService::class)->create(['base_rule_id' => $rule->id, 'year_no' => $year, 'idv_basis' => $basis]);
                }
            }
            foreach ($this->addonFields() as $field => $label) {
                $value = trim((string) ($input[$field] ?? ''));
                if ($value === '') {
                    continue;
                }
                $checked = $this->checked($value, $label);
                $numeric = is_float($checked);
                app(InsAddonRateService::class)->create(['base_rule_id' => $rule->id, 'insurance_company' => $rule->company, 'permit' => $rule->permit,
                    'addon_slug' => substr($field, 6), 'addon_name' => $label, 'rate_type' => ! $numeric ? 'formula' : ($checked < 1 ? 'idv_rate' : 'flat'),
                    'rate_value' => $numeric ? $checked : 0, 'rate_text' => $value, 'applies_on' => $numeric && $checked < 1 ? 'idv' : 'premium',
                    'wef_date' => $wef, 'is_active' => true]);
            }

            return $rule->refresh();
        });
    }

    public function remove(Model $model): void
    {
        DB::transaction(function () use ($model) {
            parent::remove($model);
            app(InsAddonRateService::class)->expireActive(now()->toDateString(), ['base_rule_id' => $model->getKey()]);
        });
    }

    public function export(string $path): int
    {
        return (int) (app(InsuranceWorkbookService::class)->export($path, ['premium'])['premium'] ?? 0);
    }

    public function import(string $path, ?string $wef = null, ?callable $progress = null): array
    {
        $result = app(InsuranceWorkbookService::class)->import($path, $wef ?? now()->toDateString(), null, ['premium']);
        $stats = $result['sheets']['premium'] ?? ['rows' => 0, 'written' => 0, 'rejected' => 0];

        return $stats + ['issues' => array_map(fn ($i) => ['row' => (int) $i['row'], 'reason' => (string) $i['reason']], array_slice($result['issues'], 0, 500))];
    }

    public function sheetTitle(): string
    {
        return InsuranceWorkbookService::SHEETS['premium'];
    }

    /** @return array<string, string> head_{key} => label */
    private function headFields(): array
    {
        $out = [];
        foreach (InsuranceWorkbookService::HEADS as $head => $label) {
            $out['head_'.$head] = $label;
        }

        return $out;
    }

    /** @return array<string, string> addon_{CODE} => name, from the add-on master */
    private function addonFields(): array
    {
        return InsAddon::query()->where('is_active', true)->orderBy('sort_order')->get(['code', 'name'])
            ->mapWithKeys(fn (InsAddon $a) => ['addon_'.strtoupper($a->code) => $a->name])->all();
    }

    /** A number (float) or a validated formula (string). */
    private function checked(string $value, string $label): float|string
    {
        if (RuleFormula::isNumber($value)) {
            return (float) $value;
        }
        try {
            RuleFormula::variables($value);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([$label => "{$label}: {$e->getMessage()}"]);
        }

        return $value;
    }
}

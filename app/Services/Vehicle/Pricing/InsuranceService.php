<?php

/**
 * Path: app/Services/Vehicle/Pricing/InsuranceService.php
 *
 * Builds every company x plan combo available for the vehicle group.
 * Standard total = base (OD+TP) + NilDep + Consumables when those addons exist.
 *
 * IDV is stored as one row per year-slot in xlr8_vehicle_pricing_ins_idv_slots
 * (base_rule_id, year_no, idv_basis text, idv_pct parsed percentage) rather
 * than fixed idv_1..idv_N columns, so a future 5+8 plan needs no schema change.
 * od = round(idv_sum * od_factor, 3) exactly per the locked Machine Spec —
 * idv_sum is already an absolute rupee figure (percent-of-invoice resolved
 * against PricingEngineService::invoiceBase() before this is called), so
 * od_factor is applied directly, with no further /100.
 */

namespace App\Services\Vehicle\Pricing;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InsuranceService
{
    /**
     * @param  array{segment?:?string,permit?:?string,fuel?:?string,wheels?:mixed,cc?:mixed,gvw?:mixed,seating?:mixed,invoice?:float}  $ctx
     */
    public function quote(array $ctx): array
    {
        $out = [
            'default_company' => null,
            'default_plan' => null,
            'standard_combo' => ['OD', 'TP', 'NILDEP', 'CONSUMABLES'],
            'selected_total' => 0.0,
            'companies' => [],
        ];

        if (! Schema::hasTable('xlr8_vehicle_pricing_ins_base_rules')) {
            return $out;
        }

        $rules = Cache::flexible('pricing.ins.base_rules', [300, 900], function () {
            $q = DB::table('xlr8_vehicle_pricing_ins_base_rules');
            if (Schema::hasColumn('xlr8_vehicle_pricing_ins_base_rules', 'deleted_at')) {
                $q->whereNull('deleted_at');
            }
            if (Schema::hasColumn('xlr8_vehicle_pricing_ins_base_rules', 'is_active')) {
                $q->where('is_active', 1);
            }

            return $q->get();
        });

        $slotsByRule = collect();
        if (Schema::hasTable('xlr8_vehicle_pricing_ins_idv_slots')) {
            $slotsByRule = Cache::flexible('pricing.ins.idv_slots', [300, 900], function () {
                $q = DB::table('xlr8_vehicle_pricing_ins_idv_slots');
                if (Schema::hasColumn('xlr8_vehicle_pricing_ins_idv_slots', 'deleted_at')) {
                    $q->whereNull('deleted_at');
                }

                return $q->orderBy('year_no')->get()->groupBy('base_rule_id');
            });
        }

        $addons = collect();
        if (Schema::hasTable('xlr8_vehicle_pricing_ins_addon_rates')) {
            $addons = Cache::flexible('pricing.ins.addon_rates', [300, 900], function () {
                $q = DB::table('xlr8_vehicle_pricing_ins_addon_rates');
                if (Schema::hasColumn('xlr8_vehicle_pricing_ins_addon_rates', 'deleted_at')) {
                    $q->whereNull('deleted_at');
                }
                if (Schema::hasColumn('xlr8_vehicle_pricing_ins_addon_rates', 'is_active')) {
                    $q->where('is_active', 1);
                }

                return $q->get();
            });
        }

        $defaults = collect();
        if (Schema::hasTable('xlr8_vehicle_pricing_ins_defaults')) {
            $defaults = DB::table('xlr8_vehicle_pricing_ins_defaults')
                ->when(Schema::hasColumn('xlr8_vehicle_pricing_ins_defaults', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->when(Schema::hasColumn('xlr8_vehicle_pricing_ins_defaults', 'is_active'), fn ($q) => $q->where('is_active', 1))
                ->get();
        }

        $invoice = (float) ($ctx['invoice'] ?? 0);
        $companies = [];

        foreach ($rules as $rule) {
            if (! $this->scopeMatch($rule, $ctx)) {
                continue;
            }

            $co = $this->ruleCompany($rule);
            $plan = (string) ($rule->plan ?? 'PLAN');
            $permit = (string) ($rule->permit ?? ($ctx['permit'] ?? ''));

            $idvSum = $this->idvSum($slotsByRule->get($rule->id, collect()), $invoice);
            $odFactor = (float) ($rule->od_factor ?? 0);
            $od = round($idvSum * $odFactor, 3);
            $tp = (float) ($rule->tp_basic ?? 0);
            $base = round($od + $tp, 2);

            $addonRows = $addons->filter(function ($a) use ($co, $permit) {
                $aco = (string) ($a->insurance_company ?? '');
                $ap = (string) ($a->permit ?? '');
                $coMatch = $aco === '' || strcasecmp($aco, $co) === 0;
                $permitMatch = $ap === '' || $ap === 'ANY' || strcasecmp($ap, $permit) === 0;

                return $coMatch && $permitMatch;
            });

            $addonList = [];
            $nildep = 0.0;
            $cons = 0.0;
            foreach ($addonRows as $a) {
                $code = strtoupper((string) ($a->addon_slug ?? $a->addon_name ?? ''));
                $amt = $this->addonAmount($a, $od, $tp, $base);
                $addonList[] = [
                    'code' => $code,
                    'amount' => $amt,
                    'selected' => in_array($code, ['NILDEP', 'NIL_DEP', 'NIL_DEPRECIATION', 'CONSUMABLES', 'CONSUMABLE'], true),
                    'frozen' => in_array($code, ['NILDEP', 'NIL_DEP', 'NIL_DEPRECIATION', 'CONSUMABLES', 'CONSUMABLE'], true),
                ];
                if (str_contains($code, 'NIL')) {
                    $nildep = $amt;
                }
                if (str_contains($code, 'CONSUM')) {
                    $cons = $amt;
                }
            }

            $standard = round($base + $nildep + $cons, 2);

            $companies[$co]['company'] = $co;
            $companies[$co]['plans'][] = [
                'plan' => $plan,
                'permit' => $permit,
                'idv_sum' => $idvSum,
                'od' => $od,
                'tp' => $tp,
                'base' => $base,
                'nildep' => $nildep,
                'consumables' => $cons,
                'standard_total' => $standard,
                'addons' => $addonList,
            ];
        }

        $out['companies'] = array_values($companies);

        $def = $defaults->firstWhere('is_default', 1) ?? $defaults->first();
        if ($def) {
            $out['default_company'] = $def->insurance_company ?? $def->company ?? null;
        } elseif ($out['companies'] !== []) {
            $out['default_company'] = $out['companies'][0]['company'];
        }

        foreach ($out['companies'] as $co) {
            if (strcasecmp((string) $co['company'], (string) $out['default_company']) !== 0) {
                continue;
            }
            $out['default_plan'] = $co['plans'][0]['plan'];
            $out['selected_total'] = (float) $co['plans'][0]['standard_total'];
            break;
        }

        return $out;
    }

    /**
     * idv_sum: absolute rupee sum across every year-slot on the matched rule.
     * A slot's idv_pct (already parsed from text like "95% of Invoice" at
     * import) is resolved against $invoice; a slot with no parseable
     * percentage is skipped rather than guessed.
     */
    protected function idvSum(Collection $slots, float $invoice): float
    {
        $sum = 0.0;
        foreach ($slots as $slot) {
            $pct = $slot->idv_pct ?? null;
            if ($pct === null || $pct === '') {
                continue;
            }
            $sum += $invoice * (float) $pct / 100;
        }

        return round($sum, 2);
    }

    protected function ruleCompany(object $rule): string
    {
        return (string) ($rule->company ?? $rule->insurance_company ?? $rule->insu_co ?? 'UNKNOWN');
    }

    /**
     * Insu Premium addon columns are formulas relative to OD/TP/base in the
     * source workbook (e.g. "=3325/$B4"), which the importer resolves to a
     * flat rate_value at import time. rate_type distinguishes a flat amount
     * from a percentage still needing resolution against the base premium.
     */
    protected function addonAmount(object $addon, float $od, float $tp, float $base): float
    {
        $rate = (float) ($addon->rate_value ?? 0);
        $type = strtoupper((string) ($addon->rate_type ?? 'FLAT'));
        $on = strtoupper((string) ($addon->applies_on ?? 'BASE'));

        if ($type !== 'PERCENT' && $type !== 'PCT' && $type !== '%') {
            return $rate;
        }

        $base_amount = match ($on) {
            'OD' => $od,
            'TP' => $tp,
            default => $base,
        };

        return round($base_amount * $rate / 100, 2);
    }

    protected function scopeMatch(object $rule, array $ctx): bool
    {
        $dims = [
            'permit' => 'permit',
            'fuel_type' => 'fuel',
            'wheels' => 'wheels',
        ];
        foreach ($dims as $ruleCol => $ctxKey) {
            if (! isset($rule->{$ruleCol})) {
                continue;
            }
            $rv = strtoupper(trim((string) $rule->{$ruleCol}));
            if ($rv === '' || $rv === 'ANY' || $rv === 'ALL') {
                continue;
            }
            $act = strtoupper(trim((string) ($ctx[$ctxKey] ?? '')));
            if ($act !== '' && ! in_array($act, array_map('trim', explode(',', $rv)), true)) {
                return false;
            }
        }

        if (! $this->rangeMatch($rule->cc_range ?? null, $ctx['cc'] ?? null)) {
            return false;
        }

        if (! $this->rangeMatch($rule->gvw_range ?? null, $ctx['gvw'] ?? null)) {
            return false;
        }

        if (! $this->rangeMatch($rule->seating ?? null, $ctx['seating'] ?? null)) {
            return false;
        }

        return true;
    }

    /**
     * cc_range/gvw_range/seating are free-text bands from the workbook:
     * "0-1000", "1001-1500", ">1500", "< 30KW", "30 - 65 KW", ">65 KW",
     * "1 to 7", "8 to 18". Blank/ANY on the rule, or no actual value to
     * test, always matches.
     */
    protected function rangeMatch(?string $range, mixed $actual): bool
    {
        $range = strtoupper(trim((string) $range));
        if ($range === '' || $range === 'ANY' || $range === 'ALL') {
            return true;
        }
        if ($actual === null || $actual === '') {
            return true;
        }
        $val = (float) $actual;

        if (preg_match('/^([\d.]+)\s*(?:-|TO)\s*([\d.]+)/', $range, $m) === 1) {
            return $val >= (float) $m[1] && $val <= (float) $m[2];
        }
        if (preg_match('/^>\s*([\d.]+)/', $range, $m) === 1) {
            return $val > (float) $m[1];
        }
        if (preg_match('/^<\s*([\d.]+)/', $range, $m) === 1) {
            return $val < (float) $m[1];
        }

        return true;
    }

    public function calculate(string|array $modelOrCtx, array $options = []): array
    {
        if (is_array($modelOrCtx)) {
            return $this->quote(array_merge($modelOrCtx, $options));
        }

        $permit = $options['permit'] ?? (($options['permits'][0] ?? null));

        return $this->quote([
            'model' => $modelOrCtx,
            'permit' => $permit,
            'invoice' => (float) ($options['ex_showroom'] ?? $options['invoice'] ?? 0),
            'ex_showroom' => (float) ($options['ex_showroom'] ?? 0),
        ]);
    }
}

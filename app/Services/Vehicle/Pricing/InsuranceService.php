<?php

/**
 * Path: app/Services/Vehicle/Pricing/InsuranceService.php
 *
 * Builds every company × plan combo available for the vehicle group.
 * Standard total = base (OD+TP) + NilDep + Consumables when those addons exist.
 */

namespace App\Services\Vehicle\Pricing;

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
            'default_plan'    => null,
            'standard_combo'  => ['OD', 'TP', 'NILDEP', 'CONSUMABLES'],
            'selected_total'  => 0.0,
            'companies'       => [],
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

        $addons = collect();
        if (Schema::hasTable('xlr8_vehicle_pricing_ins_addon_rates')) {
            $addons = Cache::flexible('pricing.ins.addon_rates', [300, 900], function () {
                $q = DB::table('xlr8_vehicle_pricing_ins_addon_rates');
                if (Schema::hasColumn('xlr8_vehicle_pricing_ins_addon_rates', 'deleted_at')) {
                    $q->whereNull('deleted_at');
                }
                return $q->get();
            });
        }

        $defaults = collect();
        if (Schema::hasTable('xlr8_vehicle_pricing_ins_defaults')) {
            $defaults = DB::table('xlr8_vehicle_pricing_ins_defaults')
                ->when(Schema::hasColumn('xlr8_vehicle_pricing_ins_defaults', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->get();
        }

        $invoice = (float) ($ctx['invoice'] ?? 0);
        $companies = [];

        foreach ($rules as $rule) {
            if (! $this->scopeMatch($rule, $ctx)) {
                continue;
            }
            $co = (string) ($rule->company ?? $rule->insu_co ?? $rule->insurer ?? 'UNKNOWN');
            $plan = (string) ($rule->plan ?? $rule->plan_name ?? 'PLAN');
            $permit = (string) ($rule->permit ?? $rule->insu_permit ?? ($ctx['permit'] ?? ''));

            $idvSum = $this->idvSum($rule, $invoice);
            $odFactor = (float) ($rule->od_factor ?? 0);
            $od = $odFactor > 0
                ? round($idvSum * $odFactor / 100, 3)
                : (float) ($rule->od_premium ?? $rule->od_amount ?? 0);
            $tp = (float) ($rule->tp_premium ?? $rule->tp_amount ?? 0);
            $base = round($od + $tp, 2);

            $addonRows = $addons->filter(function ($a) use ($co, $plan, $permit) {
                $aco = (string) ($a->company ?? $a->insu_co ?? '');
                $ap  = (string) ($a->plan ?? $a->plan_name ?? '');
                return (strcasecmp($aco, $co) === 0 || $aco === '')
                    && (strcasecmp($ap, $plan) === 0 || $ap === '');
            });

            $addonList = [];
            $nildep = 0.0;
            $cons = 0.0;
            foreach ($addonRows as $a) {
                $code = strtoupper((string) ($a->addon_code ?? $a->name ?? $a->addon ?? ''));
                $amt = (float) ($a->amount ?? $a->rate ?? 0);
                $addonList[] = [
                    'code'     => $code,
                    'amount'   => $amt,
                    'selected' => in_array($code, ['NILDEP', 'NIL_DEP', 'NIL DEPRECIATION', 'CONSUMABLES', 'CONSUMABLE'], true),
                    'frozen'   => in_array($code, ['NILDEP', 'NIL_DEP', 'NIL DEPRECIATION', 'CONSUMABLES', 'CONSUMABLE'], true),
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
                'plan'           => $plan,
                'permit'         => $permit,
                'od'             => $od,
                'tp'             => $tp,
                'base'           => $base,
                'nildep'         => $nildep,
                'consumables'    => $cons,
                'standard_total' => $standard,
                'addons'         => $addonList,
            ];
        }

        $out['companies'] = array_values($companies);

        $def = $defaults->firstWhere('is_default', 1) ?? $defaults->first();
        if ($def) {
            $out['default_company'] = $def->company ?? $def->insu_co ?? null;
            $out['default_plan'] = $def->plan ?? $def->plan_name ?? null;
            if (! empty($def->standard_combo)) {
                $combo = is_string($def->standard_combo) ? json_decode($def->standard_combo, true) : $def->standard_combo;
                if (is_array($combo) && $combo !== []) {
                    $out['standard_combo'] = $combo;
                }
            }
        } elseif ($out['companies'] !== []) {
            $out['default_company'] = $out['companies'][0]['company'];
            $out['default_plan'] = $out['companies'][0]['plans'][0]['plan'] ?? null;
        }

        foreach ($out['companies'] as $co) {
            if (strcasecmp((string) $co['company'], (string) $out['default_company']) !== 0) {
                continue;
            }
            foreach ($co['plans'] as $p) {
                if (strcasecmp((string) $p['plan'], (string) $out['default_plan']) === 0) {
                    $out['selected_total'] = (float) $p['standard_total'];
                    break 2;
                }
            }
            $out['selected_total'] = (float) ($co['plans'][0]['standard_total'] ?? 0);
        }

        return $out;
    }

    protected function idvSum(object $rule, float $invoice): float
    {
        $sum = 0.0;
        foreach (['idv_1', 'idv_2', 'idv_3', 'idv_4', 'idv_5', 'idv1', 'idv2', 'idv3'] as $col) {
            if (isset($rule->{$col}) && $rule->{$col} !== null && $rule->{$col} !== '') {
                $v = (float) $rule->{$col};
                $sum += $v > 0 && $v <= 100 ? $invoice * $v / 100 : $v;
            }
        }
        if ($sum <= 0 && isset($rule->idv_percent)) {
            $sum = $invoice * (float) $rule->idv_percent / 100;
        }

        return round($sum, 2);
    }

    protected function scopeMatch(object $rule, array $ctx): bool
    {
        foreach (['segment', 'permit', 'fuel', 'wheels'] as $col) {
            if (! isset($rule->{$col})) {
                continue;
            }
            $rv = strtoupper(trim((string) $rule->{$col}));
            if ($rv === '' || $rv === 'ANY' || $rv === 'ALL') {
                continue;
            }
            $act = strtoupper(trim((string) ($ctx[$col] ?? '')));
            if ($act !== '' && ! in_array($act, array_map('trim', explode(',', $rv)), true)) {
                return false;
            }
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
            'model'       => $modelOrCtx,
            'permit'      => $permit,
            'invoice'     => (float) ($options['ex_showroom'] ?? $options['invoice'] ?? 0),
            'ex_showroom' => (float) ($options['ex_showroom'] ?? 0),
        ]);
    }
}

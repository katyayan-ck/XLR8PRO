<?php

/**
 * Path: app/Services/Vehicle/Pricing/RtoService.php
 *
 * Most-specific matching. Blank / ANY = all descendants.
 */

namespace App\Services\Vehicle\Pricing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RtoService
{
    /**
     * @param  array{segment?:?string,model?:?string,variant?:?string,permit?:?string,wheels?:mixed,fuel?:?string,gvw?:mixed,ex_showroom?:float}  $ctx
     */
    public function quote(array $ctx): array
    {
        $empty = [
            'selected_permit' => $ctx['permit'] ?? null,
            'tax'             => 0.0,
            'trc'             => 0.0,
            'hypo'            => 0.0,
            'other'           => 0.0,
            'total'           => 0.0,
            'permit_options'  => [],
            'bifurcation'     => [],
        ];

        if (! Schema::hasTable('xlr8_vehicle_pricing_rto_rules')) {
            return $empty;
        }

        $rules = Cache::flexible('pricing.rto.rules', [300, 900], function () {
            return DB::table('xlr8_vehicle_pricing_rto_rules')
                ->where(function ($q) {
                    if (Schema::hasColumn('xlr8_vehicle_pricing_rto_rules', 'is_active')) {
                        $q->where('is_active', 1);
                    }
                })
                ->when(Schema::hasColumn('xlr8_vehicle_pricing_rto_rules', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->get();
        });

        $matched = [];
        foreach ($rules as $rule) {
            if (! $this->matches($rule, $ctx)) {
                continue;
            }
            $matched[] = $rule;
        }

        if ($matched === []) {
            return $empty;
        }

        usort($matched, fn ($a, $b) => $this->specificity($b, $ctx) <=> $this->specificity($a, $ctx));
        $best = $matched[0];

        $tax  = (float) ($best->tax_amount ?? $best->rto_tax ?? 0);
        $pct  = (float) ($best->tax_percent ?? 0);
        if ($tax <= 0 && $pct > 0) {
            $tax = round(((float) ($ctx['ex_showroom'] ?? 0)) * $pct / 100, 2);
        }
        $trc  = (float) ($best->trc ?? $best->trc_amount ?? 0);
        $hypo = (float) ($best->hypo ?? $best->hypothecation ?? 0);
        $other = (float) ($best->other_amount ?? 0);
        $permit = $best->permit ?? ($ctx['permit'] ?? null);

        $options = [];
        foreach ($matched as $r) {
            $p = strtoupper(trim((string) ($r->permit ?? '')));
            if ($p === '' || $p === 'ANY') {
                continue;
            }
            $options[$p] = $p;
        }

        return [
            'selected_permit' => $permit,
            'tax'             => $tax,
            'trc'             => $trc,
            'hypo'            => $hypo,
            'other'           => $other,
            'total'           => round($tax + $trc + $hypo + $other, 2),
            'permit_options'  => array_values($options) ?: array_filter([$permit]),
            'bifurcation'     => [
                'tax'   => $tax,
                'trc'   => $trc,
                'hypo'  => $hypo,
                'other' => $other,
                'rule_id' => $best->id ?? null,
            ],
        ];
    }

    protected function matches(object $rule, array $ctx): bool
    {
        $checks = [
            'segment' => $ctx['segment'] ?? null,
            'model'   => $ctx['model'] ?? null,
            'variant' => $ctx['variant'] ?? null,
            'permit'  => $ctx['permit'] ?? null,
            'fuel'    => $ctx['fuel'] ?? null,
        ];
        foreach ($checks as $col => $val) {
            if (! isset($rule->{$col})) {
                continue;
            }
            if (! $this->tokenMatch((string) $rule->{$col}, $val)) {
                return false;
            }
        }

        if (isset($rule->wheels) && ! $this->tokenMatch((string) $rule->wheels, $ctx['wheels'] ?? null)) {
            return false;
        }

        $gvw = $ctx['gvw'] ?? null;
        if ($gvw !== null && $gvw !== '') {
            $from = $rule->gvw_from ?? $rule->gvw_min ?? null;
            $to   = $rule->gvw_to ?? $rule->gvw_max ?? null;
            if ($from !== null && (float) $gvw < (float) $from) {
                return false;
            }
            if ($to !== null && (float) $gvw > (float) $to) {
                return false;
            }
        }

        return true;
    }

    protected function tokenMatch(string $ruleVal, mixed $actual): bool
    {
        $ruleVal = strtoupper(trim($ruleVal));
        if ($ruleVal === '' || $ruleVal === 'ANY' || $ruleVal === 'ALL') {
            return true;
        }
        if ($actual === null || $actual === '') {
            return true;
        }
        $hay = array_map(fn ($t) => strtoupper(trim($t)), explode(',', $ruleVal));
        $act = strtoupper(trim((string) $actual));

        return in_array($act, $hay, true);
    }

    protected function specificity(object $rule, array $ctx): int
    {
        $score = 0;
        foreach (['variant', 'model', 'segment', 'permit', 'fuel', 'wheels'] as $col) {
            $v = strtoupper(trim((string) ($rule->{$col} ?? '')));
            if ($v !== '' && $v !== 'ANY' && $v !== 'ALL') {
                $score += $col === 'variant' ? 32 : ($col === 'model' ? 16 : 8);
            }
        }

        return $score;
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

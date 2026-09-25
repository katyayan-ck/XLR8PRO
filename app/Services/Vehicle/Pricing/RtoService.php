<?php

/**
 * Path: app/Services/Vehicle/Pricing/RtoService.php
 *
 * Most-specific matching. Blank / ANY = all descendants.
 *
 * tax_basis / surcharge_formula hold formula TEXT from the source workbook
 * (e.g. "% of Rounded Up ESR", "12.5% of Tax"), not a value. This is an
 * explicit, closed set of known patterns — never a general expression
 * evaluator. An unrecognized pattern computes to 0 and logs loudly rather
 * than guessing, per the locked spec.
 */

namespace App\Services\Vehicle\Pricing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            'tax' => 0.0,
            'surcharge' => 0.0,
            'hypothecation' => 0.0,
            'green_tax' => 0.0,
            'registration_fee' => 0.0,
            'duplicate_tax_card' => 0.0,
            'fitness' => 0.0,
            'penalty' => 0.0,
            'rto_tape' => 0.0,
            'total' => 0.0,
            'permit_options' => [],
            'bifurcation' => [],
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

        $exShowroom = (float) ($ctx['ex_showroom'] ?? 0);
        $tax = $this->resolveTax($best, $exShowroom);
        $surcharge = $this->resolveSurcharge($best, $tax);

        $hypothecation = (float) ($best->hypothecation ?? 0);
        $greenTax = (float) ($best->green_tax ?? 0);
        $registrationFee = (float) ($best->registration_fee ?? 0);
        $duplicateTaxCard = (float) ($best->duplicate_tax_card ?? 0);
        $fitness = (float) ($best->fitness ?? 0);
        $penalty = (float) ($best->penalty ?? 0);
        $rtoTape = (float) ($best->rto_tape ?? 0);

        $permit = $best->permit ?? ($ctx['permit'] ?? null);

        $options = [];
        foreach ($matched as $r) {
            $p = strtoupper(trim((string) ($r->permit ?? '')));
            if ($p === '' || $p === 'ANY') {
                continue;
            }
            $options[$p] = $p;
        }

        $total = round(
            $tax + $surcharge + $hypothecation + $greenTax + $registrationFee
            + $duplicateTaxCard + $fitness + $penalty + $rtoTape,
            2
        );

        return [
            'selected_permit' => $permit,
            'tax' => $tax,
            'surcharge' => $surcharge,
            'hypothecation' => $hypothecation,
            'green_tax' => $greenTax,
            'registration_fee' => $registrationFee,
            'duplicate_tax_card' => $duplicateTaxCard,
            'fitness' => $fitness,
            'penalty' => $penalty,
            'rto_tape' => $rtoTape,
            'total' => $total,
            'permit_options' => array_values($options) ?: array_filter([$permit]),
            'bifurcation' => [
                'tax' => $tax,
                'surcharge' => $surcharge,
                'hypothecation' => $hypothecation,
                'green_tax' => $greenTax,
                'registration_fee' => $registrationFee,
                'duplicate_tax_card' => $duplicateTaxCard,
                'fitness' => $fitness,
                'penalty' => $penalty,
                'rto_tape' => $rtoTape,
                'rule_id' => $best->id ?? null,
            ],
        ];
    }

    /**
     * tax_basis known patterns:
     * - blank/null with a numeric tax_factor already stored → flat amount.
     * - "% of Rounded Up ESR" → round_up(ex_showroom) * tax_slab
     *   (tax_slab is already a decimal fraction, e.g. 0.1, not 10).
     * Anything else: 0 + loud log.
     */
    protected function resolveTax(object $rule, float $exShowroom): float
    {
        $basis = strtolower(trim((string) ($rule->tax_basis ?? '')));

        if ($basis === '') {
            return (float) ($rule->tax_factor ?? 0);
        }

        if (str_contains($basis, '% of rounded up esr')) {
            $slab = (float) ($rule->tax_slab ?? 0);

            return round(ceil($exShowroom) * $slab, 2);
        }

        Log::warning('RtoService: unrecognized tax_basis, tax set to 0', [
            'rule_id' => $rule->id ?? null,
            'tax_basis' => $rule->tax_basis ?? null,
        ]);

        return 0.0;
    }

    /**
     * surcharge_formula known patterns:
     * - blank/null with a numeric surcharge already stored → flat amount.
     * - "{n}% of Tax" → tax * (n/100), via the existing percentOrNum() extractor.
     * Anything else: 0 + loud log.
     */
    protected function resolveSurcharge(object $rule, float $tax): float
    {
        $formula = strtolower(trim((string) ($rule->surcharge_formula ?? '')));

        if ($formula === '') {
            return (float) ($rule->surcharge ?? 0);
        }

        if (str_contains($formula, '% of tax')) {
            $pct = RulesWorkbookService::percentOrNum($rule->surcharge_formula);
            if ($pct !== null) {
                return round($tax * $pct / 100, 2);
            }
        }

        Log::warning('RtoService: unrecognized surcharge_formula, surcharge set to 0', [
            'rule_id' => $rule->id ?? null,
            'surcharge_formula' => $rule->surcharge_formula ?? null,
        ]);

        return 0.0;
    }

    protected function matches(object $rule, array $ctx): bool
    {
        $checks = [
            'permit' => $ctx['permit'] ?? null,
            'fuel_type' => $ctx['fuel'] ?? null,
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
        if ($gvw !== null && $gvw !== '' && ! empty($rule->gvw_range)) {
            [$from, $to] = $this->parseGvwRange((string) $rule->gvw_range);
            if ($from !== null && (float) $gvw < $from) {
                return false;
            }
            if ($to !== null && (float) $gvw > $to) {
                return false;
            }
        }

        return true;
    }

    /**
     * gvw_range is a single free-text range string (e.g. "0-3500", "3500+").
     * Only the two shapes actually seen in the workbook are parsed; anything
     * else is treated as unbounded (no filtering) rather than guessed.
     *
     * @return array{0:?float,1:?float}
     */
    protected function parseGvwRange(string $range): array
    {
        $range = trim($range);

        if (preg_match('/^([\d.]+)\s*-\s*([\d.]+)$/', $range, $m) === 1) {
            return [(float) $m[1], (float) $m[2]];
        }

        if (preg_match('/^([\d.]+)\s*\+$/', $range, $m) === 1) {
            return [(float) $m[1], null];
        }

        return [null, null];
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
        foreach (['permit', 'fuel_type', 'wheels', 'gvw_range', 'cc_range', 'seater'] as $col) {
            $v = strtoupper(trim((string) ($rule->{$col} ?? '')));
            if ($v !== '' && $v !== 'ANY' && $v !== 'ALL') {
                $score += $col === 'permit' ? 16 : 8;
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
            'model' => $modelOrCtx,
            'permit' => $permit,
            'ex_showroom' => (float) ($options['ex_showroom'] ?? 0),
        ]);
    }
}

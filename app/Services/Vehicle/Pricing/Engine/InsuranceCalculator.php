<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Engine;

use App\Models\Vehicle\Pricing\InsBaseRule;
use App\Services\Vehicle\Pricing\Rules\RuleFormula;
use Illuminate\Support\Collection;

/**
 * Insurance premiums for one vehicle and insurance permit (locked spec §7.3, DEC-078 / DEC-080): every company × plan.
 *
 *   IDV year n  = ex-showroom × slot n %                 OD gross = Σ IDV (plan's OD years) × OD factor
 *   OD          = OD gross − OD discount (setting, 30%)  + CNG / LPG kit ("5% x OD", gas vehicles) + IMT 23 when present
 *   TP          = basic + per passenger ("n x (Seat -1)") + bi-fuel kit (gas) + compulsory PA owner + LL driver
 *                 + LL non-fare passengers; "PA cover for passengers" is an option
 *   add-ons     idv_rate × IDV year 1 / flat / formula; NilDep + Consumables are the default ("standard") combo
 *   GST         18% on OD, add-ons and TP; Goods TP 12% (settings)
 * A plan row without an OD factor / heads / add-on rates takes them from its sibling row (same company and scope,
 * another plan) — the reference "3+3" rows carry only TP and IDV slots.
 */
final class InsuranceCalculator
{
    /** fallback when the add-on master marks none as default (DEC-080) */
    public const DEFAULT_ADDONS = ['NIL_DEP', 'CONSUMABLES'];

    public const OPTIONAL_HEADS = ['tp_pa_passengers'];

    public function __construct(private readonly RuleBook $book) {}

    /**
     * @return array{insu_permit: string, default: array<string, mixed>, companies: list<array<string, mixed>>}|null null when no rule matches
     */
    public function calculate(VehicleFacts $v, string $insuPermit, float $exShowroom, string $modelCode): ?array
    {
        $permits = [$insuPermit, $this->book->canonical('Permit', $insuPermit)];
        $fuel = $v->isElectric() ? 'EV' : 'ICE';
        $matching = ScopeMatcher::all($this->book->insurance, [
            'permit' => $permits, 'wheels' => [(string) $v->wheels], 'fuel_type' => [$fuel, $this->book->canonical('Fuel', $fuel)],
        ], [
            'cc_range' => $v->isElectric() ? $v->motorKw : $v->cc, 'gvw_range' => $v->gvw, 'seating' => $v->seating,
        ]);
        if ($matching->isEmpty()) {
            return null;
        }

        $companies = [];
        foreach ($this->companyOrder($matching, $modelCode, $permits, $v->segment) as $i => $company) {
            $plans = [];
            // per plan the most specific row (ScopeMatcher::all() is most specific first)
            foreach ($matching->where('company', $company)->groupBy(fn (InsBaseRule $r) => (string) $r->plan) as $rows) {
                $plans[] = $this->plan($v, $rows->first(), $matching->where('company', $company), $exShowroom, $insuPermit);
            }
            usort($plans, fn ($a, $b) => [$a['od_years'], $a['tp_years']] <=> [$b['od_years'], $b['tp_years']]);
            $companies[] = ['company' => $company, 'default' => $i === 0, 'plans' => $plans];
        }

        $plan = $companies[0]['plans'][0];
        $defaults = array_values(array_filter($plan['addons'], fn ($a) => $a['default']));
        $addonsNet = array_sum(array_column($defaults, 'premium'));
        $odGst = round(($plan['od'] + $plan['od_heads_total'] + $addonsNet) * $this->book->insuranceGstPct / 100);
        $gst = $odGst + $plan['tp_gst'];

        return [
            'insu_permit' => $insuPermit,
            'default' => [
                'company' => $companies[0]['company'], 'plan' => $plan['plan'], 'addons' => array_column($defaults, 'code'),
                'od' => $plan['od'] + $plan['od_heads_total'], 'tp' => $plan['tp'], 'addons_total' => $addonsNet,
                'gst' => $gst, 'total' => $plan['od'] + $plan['od_heads_total'] + $plan['tp'] + $addonsNet + $gst, 'frozen' => true,
            ],
            'companies' => $companies,
        ];
    }

    /**
     * Companies in the preferred order (InsDefault: the model's rows, else the segment + permit rows, else ANY — DEC-083),
     * then any other company with a rule.
     *
     * @param  Collection<int, InsBaseRule>  $matching
     * @param  list<string|null>  $permits
     * @return list<string>
     */
    private function companyOrder(Collection $matching, string $modelCode, array $permits, ?string $segment = null): array
    {
        $permits = array_filter(array_map(fn ($p) => $p === null ? null : strtoupper($p), $permits));
        $defaults = $this->book->insuranceDefaults->filter(fn ($d) => $d->permit === null || in_array(strtoupper($d->permit), $permits, true));
        $own = $defaults->filter(fn ($d) => strtoupper((string) $d->model_code) === strtoupper($modelCode));
        $any = $defaults->filter(fn ($d) => strtoupper((string) $d->model_code) === 'ANY');
        $bySegment = $any->filter(fn ($d) => $segment !== null && strtoupper((string) $d->segment) === strtoupper($segment));
        $ordered = ($own->isNotEmpty() ? $own : ($bySegment->isNotEmpty() ? $bySegment : $any->filter(fn ($d) => blank($d->segment) || strtoupper((string) $d->segment) === 'ANY')))
            ->sortBy('priority')->pluck('insurance_company')->all();
        $withRules = $matching->pluck('company')->unique()->values()->all();

        return array_values(array_unique(array_merge(array_values(array_intersect($ordered, $withRules)), $withRules)));
    }

    /**
     * @param  Collection<int, InsBaseRule>  $companyRows  the company's matching rows (siblings for missing values)
     * @return array<string, mixed>
     */
    private function plan(VehicleFacts $v, InsBaseRule $rule, Collection $companyRows, float $ex, string $insuPermit): array
    {
        $siblings = $companyRows->filter(fn (InsBaseRule $r) => $r->id !== $rule->id
            && [$r->cc_range, $r->gvw_range, $r->seating, $r->fuel_type, $r->wheels] == [$rule->cc_range, $rule->gvw_range, $rule->seating, $rule->fuel_type, $rule->wheels]);
        $factor = (float) $rule->od_factor > 0 ? (float) $rule->od_factor : (float) ($siblings->first(fn ($r) => (float) $r->od_factor > 0)->od_factor ?? 0);
        $heads = array_replace(...array_merge($siblings->map(fn ($r) => (array) $r->heads)->values()->all(), [(array) $rule->heads]));
        $rates = $this->book->addonRates->get($rule->id) ?? collect();
        if ($rates->isEmpty()) {
            $rates = $siblings->map(fn ($r) => $this->book->addonRates->get($r->id))->filter()->first() ?? collect();
        }

        $idv = [];
        foreach (($this->book->idvSlots->get($rule->id) ?? collect())->take(max(1, (int) $rule->od_years)) as $slot) {
            $pct = (float) $slot->idv_pct;
            $idv[] = ['year' => (int) $slot->year_no, 'pct' => $pct, 'amount' => round($ex * $pct / 100)];
        }
        $idv1 = $idv[0]['amount'] ?? round($ex * 0.95);
        $odGross = round(array_sum(array_column($idv, 'amount')) * $factor);
        $odDiscount = round($odGross * $this->book->odDiscountPct / 100);
        $od = $odGross - $odDiscount;
        $seat = (float) ($v->seating ?? 1);

        $cng = $v->hasGasKit() && isset($heads['od_cng_kit']) ? $this->value($heads['od_cng_kit'], ['OD' => $od, 'SEAT' => $seat, 'IDV' => $idv1]) : 0.0;
        $imt = isset($heads['od_imt23']) ? $this->value($heads['od_imt23'], ['OD' => $od, 'LPG' => $cng, 'SEAT' => $seat, 'IDV' => $idv1]) : 0.0;
        $tpHeads = [];
        foreach (['tp_basic', 'tp_per_pass', 'tp_bi_fuel', 'tp_pa_owner', 'tp_ll_driver', 'tp_ll_non_fare', 'tp_pa_passengers'] as $head) {
            if (! isset($heads[$head]) || ($head === 'tp_bi_fuel' && ! $v->hasGasKit())) {
                continue;
            }
            $tpHeads[$head] = $this->value($heads[$head], ['OD' => $od, 'SEAT' => $seat, 'IDV' => $idv1]);
        }
        $tp = array_sum(array_diff_key($tpHeads, array_flip(self::OPTIONAL_HEADS)));
        $tpGstPct = $v->isGoods($insuPermit) ? $this->book->goodsTpGstPct : $this->book->insuranceGstPct;

        $addons = [];
        foreach ($rates as $rate) {
            $text = $rate->rate_text ?? (string) $rate->rate_value;
            $premium = match ($rate->rate_type) {
                'idv_rate' => round((float) $text * $idv1),
                'flat' => round((float) $text),
                default => $this->value($text, ['OD' => $od, 'IDV' => $idv1, 'SEAT' => $seat]),
            };
            $code = strtoupper($rate->addon_slug);
            $addons[] = ['code' => $code, 'name' => $this->book->insuranceAddonNames[$code] ?? (string) $rate->addon_name, 'premium' => $premium,
                'default' => in_array($code, $this->book->defaultInsuranceAddons, true)];   // the add-on master's default combo (DEC-083)
        }
        $odPart = $od + $cng + $imt;
        $baseGst = round($odPart * $this->book->insuranceGstPct / 100) + round($tp * $tpGstPct / 100);
        $standardAddons = array_sum(array_column(array_filter($addons, fn ($a) => $a['default']), 'premium'));

        return [
            'plan' => (string) $rule->plan, 'od_years' => (int) $rule->od_years, 'tp_years' => (int) $rule->tp_years, 'rule_id' => $rule->id,
            'idv' => $idv, 'od_factor' => $factor, 'od_gross' => $odGross, 'od_discount' => $odDiscount, 'od' => $od,
            'od_heads' => ['cng_kit' => $cng, 'imt23' => $imt], 'od_heads_total' => $cng + $imt,
            'tp' => $tp, 'tp_heads' => $tpHeads, 'tp_gst' => round($tp * $tpGstPct / 100),
            'od_gst_pct' => $this->book->insuranceGstPct, 'tp_gst_pct' => $tpGstPct,   // frozen with the snapshot (getPricing re-prices selections)
            'gst' => $baseGst, 'base_total' => $odPart + $tp + $baseGst,
            'addons' => $addons,
            'standard_total' => $odPart + $tp + $standardAddons + round(($odPart + $standardAddons) * $this->book->insuranceGstPct / 100) + round($tp * $tpGstPct / 100),
        ];
    }

    /** @param array<string, float> $vars */
    private function value(mixed $head, array $vars): float
    {
        return round(RuleFormula::isNumber($head) ? (float) $head : RuleFormula::evaluate($head, $vars));
    }
}

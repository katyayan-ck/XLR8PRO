<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Engine;

use App\Models\Vehicle\Pricing\RtoRule;
use App\Services\Vehicle\Pricing\Rules\RuleFormula;

/**
 * RTO charges for one vehicle and RTO permit (locked spec §7.4, DEC-080).
 *
 *   rule   the most specific live rule on permit, wheels, registration type, body type, fuel, GVW, seater, CC and the
 *          assessable band (BH)
 *   tax    "% of Rounded Up ESR"  → ex-showroom rounded up to ₹1,000 (setting) × tax factor
 *          "… Ass Value + Dlr Mgn" → (assessable + dealer margin) rounded up × tax factor (BH)
 *          "Fixed"                → the tax factor is the amount
 *   total  tax + surcharge ("12.5% of Tax") + hypothecation + green tax + registration + duplicate tax card + fitness
 *          + penalty; "Outside State – TRC" is shown but not in the default total
 * Default registration is Regular; a BH computation is added under options[] when a BH rule matches.
 */
final class RtoCalculator
{
    public function __construct(private readonly RuleBook $book) {}

    /**
     * @return array<string, mixed>|null the contract's rto block, or null when no rule matches
     */
    public function calculate(VehicleFacts $v, string $rtoPermit, float $exShowroom, float $assessable, float $dealerMargin): ?array
    {
        $regular = $this->forRegType($v, $rtoPermit, 'REGULAR', $exShowroom, $assessable, $dealerMargin);
        if ($regular === null) {
            return null;
        }
        $bh = $this->forRegType($v, $rtoPermit, 'BH', $exShowroom, $assessable, $dealerMargin, requireRegType: true);
        $regular['options'] = $bh !== null ? [array_diff_key($bh, ['options' => 1])] : [];

        return $regular;
    }

    /** @return array<string, mixed>|null */
    private function forRegType(VehicleFacts $v, string $rtoPermit, string $regType, float $ex, float $assessable, float $margin, bool $requireRegType = false): ?array
    {
        $bhBase = $this->roundUp($assessable + $margin);
        $candidates = $requireRegType
            ? $this->book->rto->filter(fn (RtoRule $r) => strtoupper(trim((string) $r->reg_type)) === $regType)
            : $this->book->rto;
        $rule = ScopeMatcher::best($candidates, [
            'permit' => [$rtoPermit, $this->book->canonical('Permit', $rtoPermit)],
            'wheels' => [(string) $v->wheels],
            'reg_type' => [$regType],
            'body_type' => [$v->bodyType],
            'fuel_type' => [$v->fuel, $v->fuelFor($this->book->synonyms)],
        ], [
            'gvw_range' => $v->gvw,
            'seater' => $v->seating,
            'cc_range' => $v->cc,
            'assessable_range' => $bhBase,
        ]);
        if (! $rule instanceof RtoRule) {
            return null;
        }

        $basis = strtoupper((string) $rule->tax_basis);
        $factor = (float) $rule->tax_factor;
        $base = str_contains($basis, 'ASS VALUE') ? $bhBase : $this->roundUp($ex);
        $tax = str_contains($basis, 'FIXED') ? $factor : $base * $factor;
        $tax = round($tax);
        $surcharge = 0.0;
        if (trim((string) $rule->surcharge_formula) !== '') {
            try {
                $surcharge = round(RuleFormula::evaluate($rule->surcharge_formula, ['TAX' => $tax]));
            } catch (\InvalidArgumentException) {
                $surcharge = round($tax * (float) $rule->surcharge / 100);
            }
        }
        $fees = [
            'hypothecation' => (float) $rule->hypothecation, 'green_tax' => (float) $rule->green_tax,
            'registration_fee' => (float) $rule->registration_fee, 'duplicate_tax_card' => (float) $rule->duplicate_tax_card,
            'fitness' => (float) $rule->fitness, 'penalty' => (float) $rule->penalty,
        ];

        return [
            'rto_permit' => $rtoPermit, 'reg_type' => $regType === 'BH' ? 'BH' : 'Regular', 'rule_id' => $rule->id,
            'base' => str_contains($basis, 'FIXED') ? 0.0 : $base, 'tax' => $tax, 'surcharge' => $surcharge,
        ] + $fees + [
            'outside_state_trc' => (float) $rule->rto_tape,
            'total' => $tax + $surcharge + array_sum($fees),
        ];
    }

    private function roundUp(float $amount): float
    {
        $step = $this->book->roundUpTo;

        return $amount <= 0 ? 0.0 : (float) (ceil($amount / $step) * $step);
    }
}

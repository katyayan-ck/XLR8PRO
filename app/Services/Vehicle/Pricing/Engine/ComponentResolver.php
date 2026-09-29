<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Engine;

use App\Models\Vehicle\Pricing\Addon;
use App\Models\Vehicle\Pricing\DealerCharge;
use App\Models\Vehicle\Pricing\Discount;
use Illuminate\Support\Collection;

/**
 * Dealer charges, RSA, Shield and the conditional discounts (exchange, corporate) for one vehicle (locked spec §6–7.1,
 * DEC-077 / DEC-080). Every block follows the matching rule "most specific row wins" (a row is its whole group):
 *
 *   dealerCharges($v, $permit)  the winning row's heads (incidental, FASTag, TRC, RTO tape, COD, Kazam)
 *   rsa($v)                     options by years from the most specific RSA scope; default = the first paid 1-year option
 *   shield($v)                  schemes from the most specific Shield scope; default = scheme 1
 *   discountOptions($v, $type)  exchange / loyalty schemes, corporate categories, each from its most specific row (none selected)
 */
final class ComponentResolver
{
    public function __construct(private readonly RuleBook $book) {}

    /** @return array<string, mixed> */
    public function dealerCharges(VehicleFacts $v, string $permit): array
    {
        $row = ScopeMatcher::best($this->book->dealerCharges, [
            'segment' => [$v->segment, $v->subSegment],
            'permit' => [$permit, $this->book->canonical('Permit', $permit)],
            'model_code' => array_merge([$v->modelCode], $v->modelNames),
        ]);
        $heads = ['incidental' => 0.0, 'fastag' => 0.0, 'trc' => 0.0, 'rto_tape' => 0.0, 'cod' => 0.0, 'kazam' => 0.0];
        if ($row instanceof DealerCharge) {
            foreach (array_keys($heads) as $head) {
                $heads[$head] = (float) $row->{$head};
            }
        }

        // COD is shown but only counted when the setting says so (user, DEC-080)
        $total = array_sum($heads) - ($this->book->includeCod ? 0 : $heads['cod']);

        return $heads + ['cod_in_total' => $this->book->includeCod, 'total' => $total, 'rule_id' => $row?->id];
    }

    /** @return array<string, mixed> */
    public function rsa(VehicleFacts $v): array
    {
        $rows = $this->group($this->book->rsa, fn (Addon $a) => strtoupper(($a->segment ?: 'ANY').'|'.($a->model_code ?: 'ANY')), [
            'segment' => [$v->segment, $v->subSegment], 'model_code' => array_merge([$v->modelCode], $v->modelNames),
        ]);
        $options = $rows->sortBy('tenure_years')->values()->map(fn (Addon $a) => [
            'years' => (int) $a->tenure_years, 'name' => (string) $a->scheme_name, 'amount' => (float) $a->amount, 'default' => false,
        ])->all();
        $default = collect($options)->first(fn ($o) => $o['years'] === 1 && $o['amount'] > 0) ?? collect($options)->first(fn ($o) => $o['amount'] > 0);
        foreach ($options as &$o) {
            $o['default'] = $default !== null && $o['years'] === $default['years'];
        }

        return ['selected_years' => $default['years'] ?? 0, 'selected_amount' => (float) ($default['amount'] ?? 0),
            'standard_coverage' => $rows->first()?->name, 'options' => $options];
    }

    /** @return array<string, mixed> */
    public function shield(VehicleFacts $v): array
    {
        $rows = $this->group($this->book->shield, fn (Addon $a) => strtoupper(implode('|', [$a->model_code ?: 'ANY', $a->variant_code ?: 'ANY', $a->shield_pack ?: 'ANY', $a->transmission ?: 'ANY', $a->fuel ?: 'ANY'])), [
            'model_code' => array_merge([$v->modelCode], $v->modelNames), 'variant_code' => [$v->code],
            'shield_pack' => [$v->shieldPack], 'transmission' => [$v->transmission], 'fuel' => [$v->fuel, $v->fuelFor($this->book->synonyms)],
        ]);
        $options = $rows->sortBy('tenure_years')->values()->map(fn (Addon $a, int $i) => [
            'scheme' => $i + 1, 'name' => (string) $a->scheme_name, 'amount' => (float) $a->amount, 'default' => $i === 0,
        ])->all();

        return ['selected_scheme' => $options === [] ? null : 1, 'selected_amount' => (float) ($options[0]['amount'] ?? 0),
            'standard_warranty' => $rows->first()?->name, 'options' => $options];
    }

    /**
     * @param  'EXCHANGE'|'CORPORATE'|'LOYALTY'  $type
     * @return list<array<string, mixed>>
     */
    public function discountOptions(VehicleFacts $v, string $type): array
    {
        $rows = match ($type) {
            'EXCHANGE' => $this->book->exchange,
            'LOYALTY' => $this->book->loyalty,
            default => $this->book->corporate,
        };
        $label = $type === 'CORPORATE' ? 'category' : 'scheme_name';
        $out = [];
        foreach ($rows->groupBy(fn (Discount $d) => strtoupper((string) $d->{$label})) as $option) {
            $row = ScopeMatcher::best($option, ['model_code' => array_merge([$v->modelCode], $v->modelNames), 'variant_code' => [$v->code]]);
            if ($row instanceof Discount) {
                $total = (float) ($row->total_discount ?? ((float) $row->oem_share + (float) $row->dealer_share));
                $out[] = [$type === 'CORPORATE' ? 'category' : 'scheme' => (string) $row->{$label}, 'oem' => (float) $row->oem_share, 'dealer' => (float) $row->dealer_share, 'total' => $total];
            }
        }

        return $out;
    }

    /**
     * The rows of the most specific matching scope group (a specific row replaces the whole general group).
     *
     * @param  Collection<int, Addon>  $rows
     * @param  callable(Addon): string  $groupKey
     * @param  array<string, list<string|null>>  $tokens
     * @return Collection<int, Addon>
     */
    private function group(Collection $rows, callable $groupKey, array $tokens): Collection
    {
        $best = ScopeMatcher::best($rows, $tokens);

        return $best === null ? collect() : $rows->filter(fn (Addon $a) => $groupKey($a) === $groupKey($best))->values();
    }
}

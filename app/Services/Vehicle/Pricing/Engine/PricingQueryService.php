<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Engine;

use App\Models\Vehicle\Pricing\Snapshot;
use App\Services\Vehicle\Pricing\PricingHoldService;
use App\Support\Result;

/**
 * Step 11 — getPricing (DEC-073 / DEC-080): the published snapshot of a vehicle as the fixed-key contract (v2), with
 * the caller's selections applied on top and the totals recomputed exactly as Calculate & Publish does. Nothing is
 * calculated from live rules here — prices are what was published.
 *
 *   getPricing('AZ1116YGTTA4EA01BZ', [
 *       'permit' => 'PRIVATE', 'vin_type' => 'NV', 'channel' => 'normal', 'wef_date' => '2026-10-15',
 *       'rsa_years' => 2, 'shield_scheme' => 0, 'insurance' => ['company' => 'USGI', 'plan' => '3+3', 'addons' => ['NIL_DEP']],
 *       'reg_type' => 'BH', 'outside_state' => false, 'include_cod' => false, 'exchange' => 'Scrappage', 'corporate' => 'CAT A',
 *   ]);
 *   → Result ok ['pricing' => contract] · fail NOT_FOUND · fail ON_HOLD (data.pricing with hold = true)
 * Unknown selections are ignored and reported in pricing.errors[].
 */
class PricingQueryService
{
    public function __construct(private readonly PricingHoldService $holds) {}

    /** @param array<string, mixed> $options */
    public function getPricing(string $oemCode, array $options = []): Result
    {
        $code = strtoupper(preg_replace('/\s+/', '', $oemCode) ?? $oemCode);
        $snapshot = $this->snapshot($code, $options);
        if (! $snapshot) {
            return Result::fail('NOT_FOUND', "No published price for {$code} with these options.");
        }
        $payload = $this->apply(PricingContract::normalize((array) $snapshot->payload), $options);
        $payload['source'] = 'snapshot';

        $held = $this->holds->isHeld((string) ($payload['price_list'] ?? ''), (string) $payload['channel'], (string) $payload['permit'], $payload['taxi_price'] === 'YES' && $payload['vehicle_permit'] !== $payload['permit']);
        if ($held) {
            $payload['hold'] = true;

            return Result::fail('ON_HOLD', 'This price list is on hold — quotations and bookings are paused until it is reopened.', ['pricing' => $payload]);
        }

        return Result::ok(['pricing' => $payload], 'Pricing ready.');
    }

    /**
     * The snapshot valid on the date: WEF on or before it, not expired by then (default: today, the vehicle's own
     * permit, NV, normal channel).
     *
     * @param  array<string, mixed>  $options
     */
    private function snapshot(string $code, array $options): ?Snapshot
    {
        $date = (string) ($options['wef_date'] ?? now()->toDateString());
        $query = Snapshot::query()->where('model_code', $code)
            ->where('vin_type', strtoupper((string) ($options['vin_type'] ?? 'NV')))
            ->where('channel', strtolower((string) ($options['channel'] ?? 'normal')))
            ->whereDate('wef_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('expired_on')->orWhereDate('expired_on', '>', $date))
            ->orderByDesc('wef_date');
        $rows = $query->get();
        if ($rows->isEmpty()) {
            return null;
        }
        $wef = $rows->first()->wef_date?->toDateString();
        $rows = $rows->filter(fn (Snapshot $s) => $s->wef_date?->toDateString() === $wef);
        if (! empty($options['permit'])) {
            return $rows->first(fn (Snapshot $s) => strtoupper($s->permit) === strtoupper((string) $options['permit']));
        }

        // default: the vehicle's own permit (a taxi-priced private car's PRIVATE snapshot, not the extra Passenger one)
        return $rows->first(fn (Snapshot $s) => strtoupper($s->permit) === strtoupper((string) ($s->payload['vehicle_permit'] ?? ''))) ?? $rows->first();
    }

    /**
     * @param  array<string, mixed>  $p  a normalized snapshot payload
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    public function apply(array $p, array $o): array
    {
        $errors = [];

        if (array_key_exists('rsa_years', $o)) {
            $years = (int) $o['rsa_years'];
            $option = collect($p['rsa']['options'])->firstWhere('years', $years);
            if ($years !== 0 && ! $option) {
                $errors[] = "RSA {$years} year(s) is not offered.";
            } else {
                $p['rsa']['selected_years'] = $years;
                $p['rsa']['selected_amount'] = (float) ($option['amount'] ?? 0);
            }
        }
        if (array_key_exists('shield_scheme', $o)) {
            $scheme = (int) $o['shield_scheme'];
            $option = collect($p['shield']['options'])->firstWhere('scheme', $scheme);
            if ($scheme !== 0 && ! $option) {
                $errors[] = "Shield scheme {$scheme} is not offered.";
            } else {
                $p['shield']['selected_scheme'] = $scheme ?: null;
                $p['shield']['selected_amount'] = (float) ($option['amount'] ?? 0);
            }
        }
        if (! empty($o['insurance']) && is_array($o['insurance'])) {
            $errors = array_merge($errors, $this->insurance($p, $o['insurance']));
        }
        if (strtoupper((string) ($o['reg_type'] ?? '')) === 'BH') {
            $bh = $p['rto']['options'][0] ?? null;
            if ($bh) {
                $p['rto'] = array_merge($p['rto'], $bh, ['options' => $p['rto']['options']]);
            } else {
                $errors[] = 'BH registration is not available for this vehicle.';
            }
        }
        if (! empty($o['outside_state'])) {
            $p['rto']['total'] += (float) $p['rto']['outside_state_trc'];
        }
        if (! empty($o['include_cod']) && ! $p['dealer_charges']['cod_in_total']) {
            $p['dealer_charges']['total'] += (float) $p['dealer_charges']['cod'];
            $p['dealer_charges']['cod_in_total'] = true;
        }
        foreach (['exchange' => 'scheme', 'corporate' => 'category'] as $type => $key) {
            if (empty($o[$type])) {
                continue;
            }
            $option = collect($p['discounts'][$type]['options'])->first(fn ($x) => strtoupper((string) $x[$key]) === strtoupper((string) $o[$type]));
            if ($option) {
                $p['discounts'][$type]['selected'] = $option[$key];
                $p['discounts'][$type]['amount'] = (float) $option['total'];
            } else {
                $errors[] = ucfirst($type)." \"{$o[$type]}\" is not offered.";
            }
        }

        return $this->totals(PricingContract::normalize($p), $errors);
    }

    /**
     * @param  array<string, mixed>  $p
     * @param  array<string, mixed>  $choice  company, plan, addons[]
     * @return list<string> errors
     */
    private function insurance(array &$p, array $choice): array
    {
        $company = collect($p['insurance']['companies'])->first(fn ($c) => strtoupper($c['company']) === strtoupper((string) ($choice['company'] ?? $p['insurance']['default']['company'])));
        if (! $company) {
            return ['Insurance company "'.($choice['company'] ?? '').'" is not offered.'];
        }
        $plan = collect($company['plans'])->first(fn ($pl) => (string) $pl['plan'] === (string) ($choice['plan'] ?? $company['plans'][0]['plan'] ?? ''));
        if (! $plan) {
            return ['Insurance plan "'.($choice['plan'] ?? '').'" is not offered by '.$company['company'].'.'];
        }
        $available = collect($plan['addons'])->keyBy('code');
        $codes = array_key_exists('addons', $choice)
            ? array_values(array_filter(array_map(fn ($c) => strtoupper((string) $c), (array) $choice['addons'])))
            : $available->filter(fn ($a) => $a['default'])->keys()->all();
        $errors = [];
        foreach (array_diff($codes, $available->keys()->all()) as $missing) {
            $errors[] = "Insurance add-on {$missing} is not offered on this plan.";
        }
        $codes = array_values(array_intersect($codes, $available->keys()->all()));
        $addons = array_sum(array_map(fn ($c) => (float) $available[$c]['premium'], $codes));
        $odPart = (float) $plan['od'] + (float) ($plan['od_heads_total'] ?? 0);
        $odGstPct = (float) ($plan['od_gst_pct'] ?? setting('pricing.insurance.gst_pct', 18));
        $gst = round(($odPart + $addons) * $odGstPct / 100) + (float) $plan['tp_gst'];
        $isDefault = $company['default'] && $plan['plan'] === $p['insurance']['default']['plan'] && $codes == $p['insurance']['default']['addons'];
        $p['insurance']['default'] = [
            'company' => $company['company'], 'plan' => $plan['plan'], 'addons' => $codes, 'od' => $odPart, 'tp' => (float) $plan['tp'],
            'addons_total' => $addons, 'gst' => $gst, 'total' => $odPart + (float) $plan['tp'] + $addons + $gst, 'frozen' => $isDefault,
        ];

        return $errors;
    }

    /**
     * Totals exactly as SnapshotBuilder computes them, plus the selected conditional discounts.
     *
     * @param  array<string, mixed>  $p
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function totals(array $p, array $errors): array
    {
        $d = &$p['discounts'];
        $d['total'] = $d['consumer_scheme'] + $d['cash'] + $d['accessory'] + $d['shield'] + $d['rsa'] + $d['exchange']['amount'] + $d['corporate']['amount'];
        $ex = (float) $p['ex_showroom'];
        $tcs = &$p['tcs'];
        $tcs['base'] = max(0.0, $ex - $d['total']);
        $tcs['applicable'] = $tcs['limit'] > 0 && $ex >= $tcs['limit'];
        $tcs['amount'] = $tcs['applicable'] ? round($tcs['base'] * $tcs['rate'] / 100) : 0.0;
        $p['gross'] = $ex + $p['dealer_charges']['total'] + $p['rsa']['selected_amount'] + $p['shield']['selected_amount'] + $p['accessories']['amount']
            + $p['insurance']['default']['total'] + $p['rto']['total'];
        $p['invoice_value'] = $ex - $d['total'] + $tcs['amount'];
        $p['on_road'] = $p['gross'] + $tcs['amount'] - $d['total'];
        $p['errors'] = array_values(array_merge((array) $p['errors'], $errors));

        return $p;
    }
}

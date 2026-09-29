<?php

declare(strict_types=1);

namespace App\Services\Sales\Quotation;

use App\Models\Vehicle\Pricing\Snapshot;
use App\Services\Vehicle\Pricing\Engine\PricingQueryService;
use App\Services\Vehicle\VehicleService;
use App\Support\Result;
use Illuminate\Support\Collection;

/**
 * The quotation screen on published prices (DEC-082): getPricing for the chosen vehicle, mapped onto the screen's pricing
 * shape (one insurance / RTO entry per published permit), the vehicle picker options, and the server-side re-validation
 * of the discount gate and TCS on save.
 *
 *   $r = app(QuotationPricingService::class)->forVehicle('AZ1116YGTTA4EA01BZ');
 *   $r->ok ? $r->get('screen') : $r->code;   // NOT_FOUND | ON_HOLD
 */
class QuotationPricingService
{
    /** Default discount types — the screen's own (DEC-082 §3). */
    public const TYPES = ['consumer' => 'INV_OE', 'cash' => 'CN1', 'accessory' => 'INV_OE', 'shield' => 'CN1', 'rsa' => 'CN1', 'corporate' => 'INV', 'exchange' => 'CN2', 'loyalty' => 'CN2'];

    public function __construct(
        private readonly PricingQueryService $pricing,
        private readonly VehicleService $vehicles,
    ) {}

    /**
     * Published pricing of a vehicle for the quotation: `screen` (the screen's shape), `contracts` (permit => contract v2).
     * Fails NOT_FOUND, or ON_HOLD (with the same data, so the screen can still show why).
     */
    public function forVehicle(string $oemCode, ?string $date = null): Result
    {
        $options = array_filter(['wef_date' => $date]);
        $own = $this->pricing->getPricing($oemCode, $options);
        if (! $own->ok && $own->code === 'NOT_FOUND') {
            return $own;
        }
        $contract = (array) $own->get('pricing');
        $contracts = [(string) $contract['permit'] => $contract];
        foreach ($this->extraPermits((string) $contract['oem_code'], (string) $contract['permit'], $date) as $permit) {
            $extra = $this->pricing->getPricing($oemCode, $options + ['permit' => $permit]);
            if ($extra->get('pricing')) {
                $contracts[$permit] = (array) $extra->get('pricing');
            }
        }
        $data = ['oem_code' => $contract['oem_code'], 'screen' => $this->screen(array_values($contracts)), 'contracts' => $contracts, 'hold' => ! $own->ok];

        return $own->ok ? Result::ok($data, 'Pricing loaded.') : Result::fail('ON_HOLD', $own->message, $data);
    }

    /**
     * The screen's pricing shape from one or more contracts (the first is the vehicle's own permit).
     *
     * @param  list<array<string, mixed>>  $contracts
     * @return array<string, mixed>
     */
    public function screen(array $contracts): array
    {
        $p = $contracts[0];
        $dc = $p['dealer_charges'];
        $d = $p['discounts'];
        $permits = array_map(fn ($c) => $this->permitLabel((string) $c['permit']), $contracts);

        return [
            'live' => true,
            'oem_code' => $p['oem_code'],
            'wef_date' => $p['wef_date'],
            'permit' => array_map(fn ($label, $i) => ['type' => $label, 'default' => $i === 0], $permits, array_keys($permits)),
            'receivables' => [
                'exShowroom' => (float) $p['ex_showroom'],
                'insurance' => array_map(fn ($c, $i) => ['permit' => $permits[$i], 'default' => $i === 0, 'companies' => $this->companies($c)], $contracts, array_keys($contracts)),
                'RTO' => [
                    'TRC' => (float) $dc['trc'],
                    'TAX' => array_map(fn ($c, $i) => ['permit' => $permits[$i], 'default' => $i === 0, 'amount' => (float) $c['rto']['total']], $contracts, array_keys($contracts)),
                ],
                'accessories' => [],
                'maxicare' => 0,
                'shield' => $this->choices($p['shield']['options'], 'scheme', fn ($o) => (string) ($o['name'] ?: 'Scheme '.$o['scheme']), $p['shield']['selected_scheme'], 'No Shield'),
                'rsa' => $this->choices($p['rsa']['options'], 'years', fn ($o) => $o['years'].' Year', $p['rsa']['selected_years'], 'No RSA'),
                'kazam' => (float) $dc['kazam'],
                'incidental' => (float) $dc['incidental'],
                'rto-tape' => (float) $dc['rto_tape'],
                'fastag' => (float) $dc['fastag'],
                'COD' => $dc['cod_in_total'] ? (float) $dc['cod'] : 0.0,
                'tcs' => ['limit' => (float) $p['tcs']['limit'], 'rate' => (float) $p['tcs']['rate']],
            ],
            'deductibles' => [
                'oem-schemes' => (float) $d['consumer_scheme'] > 0
                    ? [['key' => 'cash_scheme_oem', 'label' => 'Cash Scheme OEM', 'amount' => (float) $d['consumer_scheme'], 'type' => self::TYPES['consumer']]]
                    : [],
                'dealer-scheme' => ['amount' => (float) $d['cash'], 'type' => self::TYPES['cash']],
                'accessory-scheme' => ['amount' => (float) $d['accessory'], 'type' => self::TYPES['accessory']],
                'shield-scheme' => ['amount' => (float) $d['shield'], 'type' => self::TYPES['shield']],
                'other-cash-discount' => ['amount' => (float) $d['rsa'], 'type' => self::TYPES['rsa']],
                'corp-scheme' => array_map(fn ($o) => ['name' => (string) $o['category'], 'amount' => (float) $o['total'], 'type' => self::TYPES['corporate']], $d['corporate']['options']),
                'exchange-scheme' => array_map(fn ($o) => ['name' => (string) $o['scheme'], 'amount' => (float) $o['total'], 'type' => self::TYPES['exchange']], $d['exchange']['options']),
                'loyalty-scheme' => array_map(fn ($o) => ['name' => (string) $o['scheme'], 'amount' => (float) $o['total'], 'type' => self::TYPES['loyalty']], $d['loyalty']['options']),
            ],
        ];
    }

    /**
     * Server-side re-validation of a submitted quotation (DEC-082 §5): the Group A gate and TCS.
     *
     * @param  array<string, mixed>  $input  the submitted form
     * @param  array{limit: float, rate: float}  $tcs
     */
    public function validateSubmission(array $input, array $tcs): Result
    {
        $sides = ['INV' => 0.0, 'INV_OE' => 0.0, 'INV_D' => 0.0, 'CN1' => 0.0, 'CN2' => 0.0, 'CN3' => 0.0];
        foreach (self::DISCOUNT_FIELDS as $field) {
            $amount = $this->amount($input[$field] ?? null);
            $type = $this->type((string) ($input[$field.'_type'] ?? ''));
            if ($amount > 0 && isset($sides[$type])) {
                $sides[$type] += $amount;
            }
        }
        $invSide = $sides['INV'] + $sides['INV_OE'] + $sides['INV_D'];
        $cnSide = $sides['CN1'] + $sides['CN2'] + $sides['CN3'];

        $groupAType = $this->type((string) ($input['group_a_type'] ?? ''));
        $groupA = $this->amount($input['group_a_amount'] ?? null);
        if (in_array($groupAType, ['INV', 'INV_OE'], true) && $groupA > 0 && $cnSide + 0.005 < $groupA) {
            return Result::fail('GATE', sprintf('Total CN discount (₹%s) must be at least the Group A scheme (₹%s) when its type is INV.', number_format($cnSide, 2), number_format($groupA, 2)));
        }

        $invoice = $this->amount($input['ex_showroom_price'] ?? null) - $invSide;
        $expected = $tcs['limit'] > 0 && $invoice >= $tcs['limit'] ? round($invoice * $tcs['rate'] / 100, 2) : 0.0;
        $submitted = $this->amount($input['tcs'] ?? null);
        if (abs($expected - $submitted) > 1) {
            return Result::fail('TCS', sprintf('TCS should be ₹%s (%s%% of invoice ₹%s), not ₹%s — reload the prices and try again.', number_format($expected, 2), $tcs['rate'], number_format($invoice, 2), number_format($submitted, 2)));
        }

        return Result::ok(['invoice' => $invoice, 'tcs' => $expected, 'inv_side' => $invSide, 'cn_side' => $cnSide]);
    }

    /**
     * Picker options: segments → models → variants (one per OEM variant) → colours with a published price.
     *
     * @return Collection<int, array{code: string, name: string}>
     */
    public function vehicleOptions(string $level, ?string $parent = null): Collection
    {
        return match ($level) {
            'segment' => $this->vehicles->segmentOptions()->map(fn ($s) => ['code' => (string) $s->code, 'name' => (string) $s->name])->values(),
            'model' => $this->vehicles->modelOptionsFor($parent)->map(fn ($m) => ['code' => (string) $m->code, 'name' => (string) $m->name])->values(),
            'variant' => $this->vehicles->variantGroupOptions($parent)->map(fn ($v) => ['code' => (string) $v['code'], 'name' => (string) $v['name']])->values(),
            'colour' => $this->pricedColours($parent),
            default => collect(),
        };
    }

    public const DISCOUNT_FIELDS = [
        'cash_scheme_oem', 'csd_discount', 'fame_subsidy', 'dealer_discount', 'accessories_discount', 'shield_scheme', 'corporate_discount',
        'loyalty_bonus', 'exchange_bonus', 'green_bonus', 'welcome_bonus', 'accessories_spl_disc', 'ceramic_discount', 'ppf_discount',
        'charger_swapping_discount', 'other_cash_discount', 'special_cash_discount',
    ];

    /** @return Collection<int, array{code: string, name: string}> */
    private function pricedColours(?string $variantCode): Collection
    {
        $colours = $this->vehicles->colorOptions($variantCode);
        $priced = Snapshot::query()->whereIn('model_code', $colours->pluck('variant_code')->all())
            ->where('vin_type', 'NV')->where('channel', 'normal')->whereDate('wef_date', '<=', now()->toDateString())
            ->where(fn ($q) => $q->whereNull('expired_on')->orWhereDate('expired_on', '>', now()->toDateString()))
            ->distinct()->pluck('model_code')->flip();

        return $colours->filter(fn ($c) => isset($priced[$c->variant_code]))
            ->map(fn ($c) => ['code' => (string) $c->variant_code, 'name' => (string) ($c->name ?: $c->code)])->values();
    }

    /** @return list<string> other permits published for the vehicle (the taxi PASSENGER price) */
    private function extraPermits(string $code, string $ownPermit, ?string $date): array
    {
        $date ??= now()->toDateString();

        return Snapshot::query()->where('model_code', $code)->where('vin_type', 'NV')->where('channel', 'normal')
            ->where('permit', '!=', $ownPermit)->whereDate('wef_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('expired_on')->orWhereDate('expired_on', '>', $date))
            ->distinct()->pluck('permit')->all();
    }

    /**
     * Insurance companies in the screen's shape: heads GST-inclusive, mandatory = the frozen default add-ons.
     *
     * @param  array<string, mixed>  $contract
     * @return list<array<string, mixed>>
     */
    private function companies(array $contract): array
    {
        $out = [];
        foreach ($contract['insurance']['companies'] as $company) {
            $plan = $company['plans'][0] ?? null;
            if (! $plan) {
                continue;
            }
            $odGst = (float) ($plan['od_gst_pct'] ?? 18);
            $heads = [['head' => 'Basic OD + TP', 'price' => (float) $plan['od'] + (float) ($plan['od_heads_total'] ?? 0) + (float) $plan['tp'] + (float) $plan['gst'], 'Nature' => 'M']];
            foreach ($plan['addons'] as $addon) {
                $heads[] = ['head' => (string) ($addon['name'] ?: $addon['code']), 'price' => round((float) $addon['premium'] * (1 + $odGst / 100)), 'Nature' => $addon['default'] ? 'M' : 'O'];
            }
            $out[] = ['insCo' => (string) $company['company'], 'plan' => (string) $plan['plan'], 'default' => (bool) $company['default'], 'price' => $heads];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return list<array{title: string, price: float, default: bool}>
     */
    private function choices(array $options, string $key, callable $title, mixed $selected, string $none): array
    {
        $out = array_map(fn ($o) => ['title' => $title($o), 'price' => (float) $o['amount'], 'default' => (string) $o[$key] === (string) $selected], $options);
        $out[] = ['title' => $none, 'price' => 0.0, 'default' => ! in_array(true, array_column($out, 'default'), true)];

        return $out;
    }

    private function permitLabel(string $permit): string
    {
        return ucfirst(strtolower($permit));
    }

    private function amount(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /** The screen's type labels, normalised as calculateDiscountBifurcationByType() does. */
    private function type(string $type): string
    {
        return match (trim($type)) {
            'Inv Disc.', 'Inv. Disc.', 'INV' => 'INV',
            'Inv Disc. (OE)', 'INV_OE' => 'INV_OE',
            'Inv Disc. (D)', 'INV_D' => 'INV_D',
            'CN', 'CN1' => 'CN1',
            default => trim($type),
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Engine;

use App\Models\Vehicle\Pricing\Snapshot;
use App\Services\Vehicle\Pricing\PricingHoldService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * The standalone Price List screens (DEC-081): one read-only list per price list, built from the NV snapshots valid on a
 * date — vehicle, add-ons (insurance / RTO bifurcation for hover), standard discounts, conditional discounts and the
 * default on-road. Rows are projected to a few fields and cached per list × date × last snapshot change.
 *
 *   app(PriceListService::class)->rows('pv', '2026-10-15');
 *   → ['list' => 'pv', 'label' => 'Passenger Vehicles', 'date' => …, 'wef' => [..dates], 'hold' => false,
 *      'exchange' => ['Bns 1', …], 'corporate' => ['CAT A', …], 'rows' => [[ 'code' => …, 'on_road' => …, … ], …]]
 */
class PriceListService
{
    /** @var array<string, array{label: string, hold: string, channel: string, price_list: string|null, taxi: bool}> */
    public const LISTS = [
        'pv' => ['label' => 'Passenger Vehicles', 'hold' => 'PV', 'channel' => 'normal', 'price_list' => 'PV', 'taxi' => false],
        'taxi' => ['label' => 'Taxi', 'hold' => 'TAXI', 'channel' => 'normal', 'price_list' => null, 'taxi' => true],
        'cv' => ['label' => 'Commercial Vehicles', 'hold' => 'CV', 'channel' => 'normal', 'price_list' => 'CV', 'taxi' => false],
        'bev' => ['label' => 'Electric', 'hold' => 'BEV', 'channel' => 'normal', 'price_list' => 'BEV', 'taxi' => false],
        'lmm' => ['label' => 'LMM', 'hold' => 'LMM', 'channel' => 'normal', 'price_list' => 'LMM', 'taxi' => false],
        'tzu' => ['label' => 'LMM TZU', 'hold' => 'LMM_TZU', 'channel' => 'normal', 'price_list' => 'LMM_TZU', 'taxi' => false],
        'csd' => ['label' => 'CSD', 'hold' => 'CSD', 'channel' => 'csd', 'price_list' => null, 'taxi' => false],
    ];

    private const CHUNK = 500;

    public function __construct(private readonly PricingHoldService $holds) {}

    /** @return array<string, mixed> */
    public function rows(string $list, ?string $date = null): array
    {
        $def = self::LISTS[$list] ?? throw new \InvalidArgumentException("Unknown price list {$list}.");
        $date ??= now()->toDateString();
        $stamp = (string) Snapshot::query()->max('updated_at').'|'.Snapshot::query()->count();

        $data = Cache::remember('price-list:'.$list.':'.$date.':'.md5($stamp), now()->addDay(), fn () => $this->build($def, $date));

        return ['list' => $list, 'label' => $def['label'], 'date' => $date, 'hold' => $this->holds->isHeld($def['hold'], $def['channel'])] + $data;
    }

    /** Vehicles per list on the date, for the landing cards. @return array<string, int> */
    public function counts(?string $date = null): array
    {
        $date ??= now()->toDateString();
        $out = [];
        foreach (self::LISTS as $list => $def) {
            $out[$list] = $this->query($def, $date)->count();
        }

        return $out;
    }

    /**
     * @param  array{label: string, hold: string, channel: string, price_list: string|null, taxi: bool}  $def
     * @return array{wef: list<string>, exchange: list<string>, corporate: list<string>, rows: list<array<string, mixed>>}
     */
    private function build(array $def, string $date): array
    {
        $rows = [];
        $exchange = $corporate = $wef = [];
        $this->query($def, $date)->select(['id', 'model_code', 'wef_date', 'payload'])
            ->chunkById(self::CHUNK, function ($chunk) use (&$rows, &$exchange, &$corporate, &$wef) {
                foreach ($chunk as $snapshot) {
                    $p = PricingContract::normalize((array) $snapshot->payload);
                    $row = $this->row($p);
                    $exchange += array_flip(array_keys($row['exchange']));
                    $corporate += array_flip(array_keys($row['corporate']));
                    $wef[(string) $snapshot->wef_date?->toDateString()] = true;
                    $rows[] = $row;
                }
            });
        usort($rows, fn ($a, $b) => [$a['model'], $a['variant'], $a['colour']] <=> [$b['model'], $b['variant'], $b['colour']]);

        return ['wef' => array_keys($wef), 'exchange' => array_map('strval', array_keys($exchange)), 'corporate' => array_map('strval', array_keys($corporate)), 'rows' => $rows];
    }

    /**
     * NV snapshots of the list valid on the date (WEF on or before it, not expired by then).
     *
     * @param  array{label: string, hold: string, channel: string, price_list: string|null, taxi: bool}  $def
     * @return Builder<Snapshot>
     */
    private function query(array $def, string $date): Builder
    {
        return Snapshot::query()
            ->where('channel', $def['channel'])
            ->where('vin_type', 'NV')
            ->whereDate('wef_date', '<=', $date)
            ->where(fn ($q) => $q->whereNull('expired_on')->orWhereDate('expired_on', '>', $date))
            ->when($def['price_list'], fn ($q, $pl) => $q->where('price_list', $pl))
            ->when($def['taxi'],
                fn ($q) => $q->where('permit', 'PASSENGER')->whereColumn('permit', '!=', 'vehicle_permit'),
                fn ($q) => $q->whereColumn('permit', 'vehicle_permit'));
    }

    /**
     * One grid row: the numbers of the default quotation plus the hover bifurcations.
     *
     * @param  array<string, mixed>  $p  a normalized contract payload
     * @return array<string, mixed>
     */
    private function row(array $p): array
    {
        $dc = $p['dealer_charges'];
        $ins = $p['insurance']['default'];
        $rto = $p['rto'];
        $d = $p['discounts'];
        $fastagTrc = (float) $dc['fastag'] + (float) $dc['trc'];
        $other = (float) $dc['total'] - (float) $dc['incidental'] - $fastagTrc;

        return [
            'code' => $p['oem_code'], 'model' => (string) $p['custom_model'], 'variant' => (string) ($p['display_name'] ?? $p['custom_variant']),
            'colour' => (string) $p['colour'], 'fuel' => $p['fuel'], 'segment' => $p['segment'], 'wef' => $p['wef_date'],
            'ex_showroom' => (float) $p['ex_showroom'],
            'incidental' => (float) $dc['incidental'], 'fastag_trc' => $fastagTrc, 'other_charges' => round($other, 2),
            'other_tip' => array_filter(['RTO tape' => (float) $dc['rto_tape'], 'Kazam' => (float) $dc['kazam'], 'COD' => $dc['cod_in_total'] ? (float) $dc['cod'] : 0.0]),
            'rsa' => (float) $p['rsa']['selected_amount'], 'rsa_years' => (int) $p['rsa']['selected_years'],
            'shield' => (float) $p['shield']['selected_amount'],
            'accessories' => (float) $p['accessories']['amount'],
            'insurance' => (float) $ins['total'],
            'insurance_tip' => [
                'title' => trim($ins['company'].' '.$ins['plan']), 'OD' => (float) $ins['od'], 'TP' => (float) $ins['tp'],
                'Add-ons ('.implode(', ', (array) $ins['addons']).')' => (float) $ins['addons_total'], 'GST' => (float) $ins['gst'],
            ],
            'rto' => (float) $rto['total'],
            'rto_tip' => ['title' => trim($rto['rto_permit'].' · '.($rto['reg_type'] ?? 'Regular'))] + array_filter([
                'Road tax' => (float) $rto['tax'], 'Surcharge' => (float) $rto['surcharge'], 'Hypothecation' => (float) $rto['hypothecation'],
                'Green tax' => (float) $rto['green_tax'], 'Registration' => (float) $rto['registration_fee'], 'Tax card' => (float) $rto['duplicate_tax_card'],
                'Fitness' => (float) $rto['fitness'], 'Penalty' => (float) $rto['penalty'],
            ]),
            'disc_consumer' => (float) $d['consumer_scheme'], 'disc_cash' => (float) $d['cash'], 'disc_accessory' => (float) $d['accessory'],
            'disc_shield' => (float) $d['shield'], 'disc_rsa' => (float) $d['rsa'], 'disc_total' => (float) $d['total'],
            'exchange' => $this->options($d['exchange']['options'], 'scheme'),
            'corporate' => $this->options($d['corporate']['options'], 'category'),
            'tcs' => (float) $p['tcs']['amount'], 'on_road' => (float) $p['on_road'], 'invoice' => (float) $p['invoice_value'],
            'hold' => (bool) $p['hold'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return array<string, float>
     */
    private function options(array $options, string $key): array
    {
        $out = [];
        foreach ($options as $o) {
            $out[(string) $o[$key]] = (float) $o['total'];
        }

        return $out;
    }
}

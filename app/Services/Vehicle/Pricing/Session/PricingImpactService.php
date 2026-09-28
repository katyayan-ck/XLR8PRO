<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Session;

use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Variant;
use App\Services\Vehicle\Pricing\Import\AddonDiscountWorkbookService;
use App\Services\Vehicle\Pricing\PricingHoldService;
use App\Services\Vehicle\VehicleCompleteness;
use App\Services\Vehicle\VehicleService;
use Illuminate\Support\Facades\DB;

/**
 * Step 7 — the impact summary (DEC-073 / DEC-079), always shown before Calculate & Publish. Computed live from the
 * session change log (what this process inserted / expired / updated) and the current masters, so it is exact.
 *
 *   summary($session) → [
 *     'vehicles'  => ['new' => n, 'activated' => n],
 *     'prices'    => ['normal' => ['new' => n, 'up' => n, 'down' => n, 'other' => n], 'csd' => [...], 'unchanged' => n],
 *     'addons'    => [group => ['written' => n, 'replaced' => n] | ['kept' => live rows]],
 *     'rules'     => ['insurance' => [...] | ['kept' => true], 'rto' => [...]],
 *     'calculate' => ['lists' => [PV => ['vehicles' => n, 'held' => bool], …, TAXI => …], 'vehicles' => n, 'held' => [lists],
 *                     'snapshots' => estimated snapshot count],
 *     'skipped'   => ['incomplete' => n, 'inactive' => n, 'no_price' => n],
 *   ]
 *   incomplete() → [[code, oem_name, segment, missing], …] (the skipped incomplete vehicles, for download)
 */
class PricingImpactService
{
    public const LISTS = ['PV', 'CV', 'BEV', 'LMM', 'LMM_TZU', 'CSD'];

    private const CHANGES = 'xlr8_vehicle_pricing_session_changes';

    public function __construct(
        private readonly PricingHoldService $holds,
        private readonly VehicleService $vehicles,
        private readonly VehicleCompleteness $completeness,
        private readonly AddonDiscountWorkbookService $addonWorkbook,
    ) {}

    /** @return array<string, mixed> */
    public function summary(ImportSession $session): array
    {
        $statusCounts = $this->vehicles->statusCounts();

        return [
            'vehicles' => $this->vehicleChanges($session),
            'prices' => $this->priceChanges($session) + ['unchanged' => (int) data_get($session->stats, 'prices.totals.unchanged', 0)],
            'addons' => $this->addons($session),
            'rules' => [
                'insurance' => data_get($session->stats, 'rules.insurance') ? array_diff_key((array) data_get($session->stats, 'rules.insurance'), ['issues' => 1]) : ['kept' => true],
                'rto' => data_get($session->stats, 'rules.rto') ? array_diff_key((array) data_get($session->stats, 'rules.rto'), ['issues' => 1]) : ['kept' => true],
            ],
            'calculate' => $this->calculable(),
            'skipped' => [
                'incomplete' => $statusCounts['INCOMPLETE'],
                'inactive' => $statusCounts['INACTIVE'] + $statusCounts['DISCONTINUED'],
                'no_price' => $this->activeWithoutPrice(),
            ],
        ];
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: string}> incomplete vehicles with their missing fields */
    public function incomplete(): array
    {
        $rows = [];
        Variant::query()->where('is_active', false)->with('vehicleModel:id,code,name')->orderBy('segment_code')->orderBy('code')
            ->chunkById(500, function ($variants) use (&$rows) {
                foreach ($variants as $v) {
                    $missing = $this->completeness->missingLabels($v);
                    if ($missing !== []) {
                        $rows[] = [$v->code, (string) $v->oem_name, (string) $v->segment_code, implode(', ', $missing)];
                    }
                }
            });

        return $rows;
    }

    /** @return array{new: int, activated: int} */
    private function vehicleChanges(ImportSession $session): array
    {
        $table = (new Variant)->getTable();
        $changes = DB::table(self::CHANGES)->where('import_session_id', $session->id)->where('table_name', $table)->get(['row_id', 'action', 'before']);
        $new = $changes->where('action', 'insert')->pluck('row_id')->unique();
        $wasInactive = $changes->where('action', 'update')
            ->filter(fn ($c) => array_key_exists('is_active', $b = (array) json_decode((string) $c->before, true)) && ! $b['is_active'])
            ->pluck('row_id')->unique();   // includes vehicles detected and completed in this process

        return [
            'new' => $new->count(),
            'activated' => $wasInactive->isEmpty() ? 0 : Variant::query()->whereKey($wasInactive->all())->where('is_active', true)->count(),
        ];
    }

    /** @return array<string, array{new: int, up: int, down: int, other: int}> per channel */
    private function priceChanges(ImportSession $session): array
    {
        $table = (new Pricing)->getTable();
        $changes = DB::table(self::CHANGES)->where('import_session_id', $session->id)->where('table_name', $table)->get(['row_id', 'action', 'before']);
        $out = ['normal' => ['new' => 0, 'up' => 0, 'down' => 0, 'other' => 0], 'csd' => ['new' => 0, 'up' => 0, 'down' => 0, 'other' => 0]];
        $inserted = $changes->where('action', 'insert')->pluck('row_id')->unique();
        $updates = $changes->where('action', 'update')->groupBy('row_id')->map(fn ($g) => (array) json_decode((string) $g->first()->before, true));

        // rows this session expired: row id => its row (the previous price of the same code / channel)
        $expiredIds = $updates->filter(fn (array $b) => ($b['is_active'] ?? null) == 1)->keys()->diff($inserted);
        $expired = Pricing::query()->whereKey($expiredIds->all())->get()->keyBy(fn (Pricing $p) => strtoupper($p->model_code.'|'.$p->channel));

        foreach (Pricing::query()->whereKey($inserted->all())->get() as $row) {
            $channel = $row->channel === 'csd' ? 'csd' : 'normal';
            $previous = $expired->get(strtoupper($row->model_code.'|'.$row->channel));
            $out[$channel][$this->direction($previous?->ex_showroom_price, $row->ex_showroom_price)]++;
        }
        // same-WEF updates of rows that existed before the session
        foreach (Pricing::query()->whereKey($updates->keys()->diff($inserted)->diff($expiredIds)->all())->get() as $row) {
            $before = $updates->get($row->id, []);
            $channel = $row->channel === 'csd' ? 'csd' : 'normal';
            $out[$channel][array_key_exists('ex_showroom_price', $before) ? $this->direction($before['ex_showroom_price'], $row->ex_showroom_price) : 'other']++;
        }

        return $out;
    }

    private function direction(mixed $before, mixed $after): string
    {
        if ($before === null) {
            return 'new';
        }
        $delta = (float) $after - (float) $before;

        return abs($delta) < 0.01 ? 'other' : ($delta > 0 ? 'up' : 'down');
    }

    /** @return array<string, array<string, mixed>> */
    private function addons(ImportSession $session): array
    {
        $run = (array) data_get($session->stats, 'addons.sheets', []);
        $live = $this->addonWorkbook->presence();
        $out = [];
        foreach ($live as $group => $count) {
            $out[$group] = isset($run[$group]) ? ['written' => (int) $run[$group]['written'], 'replaced' => (int) $run[$group]['expired']] : ['kept' => $count];
        }

        return $out;
    }

    /** @return array{lists: array<string, array{vehicles: int, held: bool}>, vehicles: int, held: list<string>, snapshots: int} */
    private function calculable(): array
    {
        $held = $this->holds->heldLists();
        $all = in_array('ALL', $held, true);
        $variantTable = (new Variant)->getTable();
        $priceTable = (new Pricing)->getTable();
        $base = fn (string $channel) => DB::table($variantTable.' as v')
            ->join($priceTable.' as p', fn ($j) => $j->on('p.model_code', '=', 'v.code')->where('p.channel', $channel)->where('p.is_active', 1)->whereNull('p.deleted_at'))
            ->where('v.is_active', 1)->whereNull('v.deleted_at');

        $byList = $base('normal')->selectRaw("COALESCE(p.price_list, 'UNLISTED') as list, count(distinct v.code) as c")->groupBy('list')->pluck('c', 'list');
        $lists = [];
        foreach (array_merge(array_diff(self::LISTS, ['CSD']), $byList->keys()->diff(self::LISTS)->all()) as $list) {
            $lists[$list] = ['vehicles' => (int) ($byList[$list] ?? 0), 'held' => $all || in_array($list, $held, true)];
        }
        $lists['CSD'] = ['vehicles' => (int) $base('csd')->distinct()->count('v.code'), 'held' => $all || in_array('CSD', $held, true)];
        $lists['TAXI'] = ['vehicles' => (int) $base('normal')->where('v.taxi_price', 'YES')->distinct()->count('v.code'), 'held' => $all || in_array('TAXI', $held, true)];

        $vehicles = 0;
        $snapshots = 0;
        foreach ($lists as $list => $row) {
            if ($row['held'] || $row['vehicles'] === 0) {
                continue;
            }
            $snapshots += $row['vehicles'] * 2;                   // NV + OV per vehicle (per extra permit / channel)
            $vehicles += $list === 'TAXI' || $list === 'CSD' ? 0 : $row['vehicles'];
        }

        return ['lists' => $lists, 'vehicles' => $vehicles, 'held' => $held, 'snapshots' => $snapshots];
    }

    private function activeWithoutPrice(): int
    {
        $priceTable = (new Pricing)->getTable();

        return Variant::query()->where('is_active', true)
            ->whereNotExists(fn ($q) => $q->from($priceTable.' as p')->whereColumn('p.model_code', (new Variant)->getTable().'.code')->where('p.channel', 'normal')->where('p.is_active', 1)->whereNull('p.deleted_at'))
            ->count();
    }
}

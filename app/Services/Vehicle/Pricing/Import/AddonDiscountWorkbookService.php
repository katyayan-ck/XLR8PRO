<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Import;

use App\Models\Vehicle\Pricing\Addon;
use App\Models\Vehicle\Pricing\AddonHistory;
use App\Models\Vehicle\Pricing\DealerCharge;
use App\Models\Vehicle\Pricing\Discount;
use App\Models\Vehicle\Pricing\DiscountHistory;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Services\Vehicle\Pricing\Addons\AddonService;
use App\Services\Vehicle\Pricing\Addons\DealerChargeService;
use App\Services\Vehicle\Pricing\Addons\DiscountService;
use App\Services\Vehicle\VehicleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Step 5 — Addon-N-Discounts.xlsx (DEC-073 / DEC-077).
 *
 *  presence()                       live rows per group (DEALER_CHARGES, RSA, SHIELD, EXCHANGE, CORPORATE, LOYALTY)
 *  export($path, $groups)           the reference sheets for the ticked groups; every applicable group row appears
 *                                   (segments / models / schemes / categories), amounts from the live rows, blank where
 *                                   nothing is stored
 *  import($path, $groups, $wef)     per ticked sheet present in the file, in one transaction: expire the group's live
 *                                   rows at the WEF, insert the sheet's rows through the entity services, write history.
 *                                   Blank = no rule, 0 = explicit zero; model names resolve to model codes ("Any" = all).
 * Run import() inside PricingSessionService::record() so Discard restores the previous rows.
 */
class AddonDiscountWorkbookService
{
    /** group => sheet title in the reference workbook */
    public const SHEETS = [
        'DEALER_CHARGES' => 'Dealer Charges - Segment Wise', 'RSA' => 'RSA', 'SHIELD' => 'Shield',
        'EXCHANGE' => 'Exchange', 'CORPORATE' => 'Corporate', 'LOYALTY' => 'Loyalty',
    ];

    /** group => export header (the reference labels) */
    public const HEADERS = [
        'DEALER_CHARGES' => ['Segment', 'Permit', 'Model', 'Incidental Charges', 'FastTag', 'TRC', 'RTO Tape', 'COD Charges'],
        'RSA' => ['Segment', 'Model', 'Standard Coverage', 'Std + 1 Year', 'Std + 2 Years', 'Std + 3 Years', 'Std + 4 Years', 'Std + 5 Years'],
        'SHIELD' => ['OEM Model', 'OEM Variant', 'Shield Pack', 'Transmission', 'Fuel', 'Standard Warranty', 'Shield Scheme 1 Name', 'Shield Scheme 1 Amt', 'Shield Scheme 2 Name', 'Shield Scheme 2 Amt'],
        'EXCHANGE' => ['OEM Model', 'OEM Variant', 'Scheme', 'Bonus OEM', 'Bonus DLR', 'Bonus TOTAL'],
        'CORPORATE' => ['OEM Model', 'OEM Variant', 'Category', 'OEM', 'DLR', 'TOTAL'],
        'LOYALTY' => ['OEM Model', 'OEM Variant', 'Scheme', 'Bonus OEM', 'Bonus DLR', 'Bonus TOTAL'],
    ];

    public const EXCHANGE_SCHEMES = ['Exchange', 'Welcome', 'Scrappage'];

    /** Loyalty works like Exchange (DEC-083): scheme name, OEM + dealer share per model / variant */
    public const LOYALTY_SCHEMES = ['Loyalty'];

    public const CORPORATE_CATEGORIES = ['CAT B', 'BULK 1 (2-5)', 'CAT A', 'BULK 2 (6-10)', 'CAT F', 'BULK 3 (11 & Above)', 'CAT Y', 'CAT Z'];

    /** segments whose models get a Shield row */
    public const SHIELD_SEGMENTS = ['PV', 'BEV', 'CV'];

    public const MAX_ISSUES = 2000;

    private const CHARGE_COLUMNS = ['incidental_charges' => 'incidental', 'fast_tag' => 'fastag', 'trc' => 'trc', 'rto_tape' => 'rto_tape', 'cod_charges' => 'cod'];

    /** @var array<string, string> model code => display name */
    private array $modelNames = [];

    public function __construct(
        private readonly PricingWorkbookReader $reader,
        private readonly VehicleService $vehicles,
        private readonly DealerChargeService $charges,
        private readonly AddonService $addons,
        private readonly DiscountService $discounts,
    ) {}

    /** "Dealer Charges - Segment Wise" / "dealer charges" → DEALER_CHARGES; null for other sheets. */
    public static function groupOf(string $title): ?string
    {
        $t = strtoupper(trim(preg_replace('/\s+/', ' ', $title) ?? $title));
        foreach (self::SHEETS as $group => $sheet) {
            if ($t === strtoupper($sheet) || $t === str_replace('_', ' ', $group)) {
                return $group;
            }
        }

        return null;
    }

    /** @return array<string, int> live rows per group */
    public function presence(): array
    {
        return [
            'DEALER_CHARGES' => DealerCharge::query()->where('is_active', true)->count(),
            'RSA' => Addon::query()->where('is_active', true)->where('addon_type', 'RSA')->count(),
            'SHIELD' => Addon::query()->where('is_active', true)->where('addon_type', 'SHIELD')->count(),
            'EXCHANGE' => Discount::query()->where('is_active', true)->where('discount_type', 'EXCHANGE')->count(),
            'CORPORATE' => Discount::query()->where('is_active', true)->where('discount_type', 'CORPORATE')->count(),
            'LOYALTY' => Discount::query()->where('is_active', true)->where('discount_type', 'LOYALTY')->count(),
        ];
    }

    // ------------------------------------------------------------------ export

    /**
     * @param  list<string>  $groups
     * @return array<string, int> rows written per group
     */
    public function export(string $path, array $groups): array
    {
        $models = $this->modelUniverse();
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        $counts = [];
        foreach (array_keys(self::SHEETS) as $group) {
            if (! in_array($group, $groups, true)) {
                continue;
            }
            $rows = match ($group) {
                'DEALER_CHARGES' => $this->dealerChargeRows($models),
                'RSA' => $this->rsaRows($models),
                'SHIELD' => $this->shieldRows($models),
                default => $this->discountRows($group, $models),
            };
            $sheet = $book->createSheet()->setTitle(self::SHEETS[$group]);
            $sheet->fromArray(array_merge([self::HEADERS[$group]], $rows), null, 'A1', true);
            $sheet->getStyle('A1:'.chr(64 + count(self::HEADERS[$group])).'1')->getFont()->setBold(true);
            $sheet->freezePane('A2');
            $counts[$group] = count($rows);
        }
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $counts;
    }

    /** @return list<array{code: string, name: string, segment: string}> models that have vehicles, by segment / name */
    private function modelUniverse(): array
    {
        $models = VehicleModel::query()
            ->whereExists(fn ($q) => $q->from((new Variant)->getTable().' as v')->whereColumn('v.model_code', 'xlr8_vehicle_model.code')->whereNull('v.deleted_at'))
            ->get(['code', 'name', 'oem_name', 'segment_code'])
            ->map(fn (VehicleModel $m) => ['code' => $m->code, 'name' => (string) ($m->oem_name ?: $m->name ?: $m->code), 'segment' => (string) $m->segment_code])
            ->sortBy(fn (array $m) => $m['segment'].'|'.strtoupper($m['name']))
            ->values()->all();
        foreach ($models as $m) {
            $this->modelNames[strtoupper($m['code'])] = $m['name'];
        }

        return $models;
    }

    private function modelLabel(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        return in_array($code, ['', 'ANY', 'ALL', '*'], true) ? 'Any' : ($this->modelNames[$code] ?? $code);
    }

    private function amount(mixed $value): float|string
    {
        return $value === null ? '' : (float) $value;
    }

    /**
     * @param  list<array{code: string, name: string, segment: string}>  $models
     * @return list<list<mixed>>
     */
    private function dealerChargeRows(array $models): array
    {
        $stored = DealerCharge::query()->where('is_active', true)->get()
            ->keyBy(fn (DealerCharge $c) => strtoupper(implode('|', [$c->segment, (string) $c->permit, $c->model_code ?: 'ANY'])));
        $rows = [];
        foreach ($this->segmentPermits($models) as $segment => $permits) {
            foreach ($permits as $permit) {
                $rows[strtoupper("{$segment}|{$permit}|ANY")] = [$segment, $permit, 'Any'];
            }
        }
        foreach ($stored as $key => $c) {
            $rows[$key] ??= [$c->segment, (string) $c->permit, $this->modelLabel($c->model_code)];
        }
        $out = [];
        foreach ($rows as $key => $row) {
            $c = $stored->get($key);
            $out[] = array_merge($row, $c ? [$this->amount($c->incidental), $this->amount($c->fastag), $this->amount($c->trc), $this->amount($c->rto_tape), $this->amount($c->cod)] : ['', '', '', '', '']);
        }

        return $out;
    }

    /**
     * Segment => permit labels: split by permit where a segment sells under more than one (taxi = Passenger), else one
     * row with a blank permit (= any).
     *
     * @param  list<array{code: string, name: string, segment: string}>  $models
     * @return array<string, list<string>>
     */
    private function segmentPermits(array $models): array
    {
        $out = [];
        foreach (array_unique(array_column($models, 'segment')) as $segment) {
            $permitIds = Variant::query()->where('segment_code', $segment)->whereNotNull('permit_id')->distinct()->pluck('permit_id')->all();
            $permits = array_filter(array_map(fn ($id) => $this->vehicles->permitCode((new Variant)->forceFill(['permit_id' => $id])), $permitIds));
            if (Variant::query()->where('segment_code', $segment)->where('taxi_price', 'YES')->exists()) {
                $permits[] = 'PASSENGER';
            }
            $permits = array_values(array_unique($permits));
            $out[$segment] = count($permits) > 1 ? array_map(fn ($p) => ucfirst(strtolower($p)), $permits) : [''];
        }

        return $out;
    }

    /**
     * @param  list<array{code: string, name: string, segment: string}>  $models
     * @return list<list<mixed>>
     */
    private function rsaRows(array $models): array
    {
        $stored = Addon::query()->where('is_active', true)->where('addon_type', 'RSA')->get()
            ->groupBy(fn (Addon $a) => strtoupper(($a->segment ?: 'ANY').'|'.($a->model_code ?: 'ANY')));
        $rows = [];
        foreach ($models as $m) {
            $rows[strtoupper($m['segment'].'|'.$m['code'])] = [$m['segment'], $m['name']];
        }
        foreach ($stored as $key => $group) {
            $first = $group->first();
            $rows[$key] ??= [(string) ($first->segment ?: 'ANY'), $this->modelLabel($first->model_code)];
        }
        $out = [];
        foreach ($rows as $key => $row) {
            $group = $stored->get($key);
            $byYears = $group ? $group->keyBy('tenure_years') : collect();
            $out[] = array_merge($row, [$group ? (string) $group->first()->name : ''], array_map(fn (int $y) => $this->amount($byYears->get($y)?->amount), [1, 2, 3, 4, 5]));
        }

        return $out;
    }

    /**
     * @param  list<array{code: string, name: string, segment: string}>  $models
     * @return list<list<mixed>>
     */
    private function shieldRows(array $models): array
    {
        $stored = Addon::query()->where('is_active', true)->where('addon_type', 'SHIELD')->orderBy('tenure_years')->get()
            ->groupBy(fn (Addon $a) => strtoupper(implode('|', [$a->model_code ?: 'ANY', $a->variant_code ?: 'ANY', $a->shield_pack ?: 'ANY', $a->transmission ?: 'ANY', $a->fuel ?: 'ANY'])));
        $out = [];
        $covered = [];
        foreach ($stored as $group) {
            $a = $group->first();
            $covered[strtoupper((string) $a->model_code)] = true;
            $schemes = $group->values();
            $out[] = [$this->modelLabel($a->model_code), $a->variant_code ?: 'Any', $a->shield_pack ?: 'ANY', $a->transmission ?: 'ANY', $a->fuel ?: 'ANY', (string) $a->name,
                (string) ($schemes[0]->scheme_name ?? ''), $this->amount($schemes[0]->amount ?? null), (string) ($schemes[1]->scheme_name ?? ''), $this->amount($schemes[1]->amount ?? null)];
        }
        foreach ($models as $m) {
            if (in_array($m['segment'], self::SHIELD_SEGMENTS, true) && ! isset($covered[strtoupper($m['code'])])) {
                $out[] = [$m['name'], 'Any', 'ANY', 'ANY', 'ANY', '', '', '', '', ''];
            }
        }

        return $out;
    }

    /**
     * @param  list<array{code: string, name: string, segment: string}>  $models
     * @return list<list<mixed>>
     */
    private function discountRows(string $type, array $models): array
    {
        $label = $type === 'CORPORATE' ? 'category' : 'scheme_name';
        $stored = Discount::query()->where('is_active', true)->where('discount_type', $type)->get()
            ->keyBy(fn (Discount $d) => strtoupper(($d->model_code ?: 'ANY').'|'.$d->{$label}));
        $options = array_values(array_unique(array_merge(
            match ($type) {
                'EXCHANGE' => self::EXCHANGE_SCHEMES,
                'LOYALTY' => self::LOYALTY_SCHEMES,
                default => self::CORPORATE_CATEGORIES,
            },
            $stored->pluck($label)->filter()->all()
        )));
        $rows = [];
        foreach ($models as $m) {
            foreach ($options as $option) {
                $rows[strtoupper($m['code'].'|'.$option)] = [$m['name'], 'Any', $option];
            }
        }
        foreach ($stored as $key => $d) {
            $rows[$key] ??= [$this->modelLabel($d->model_code), $d->variant_code ?: 'Any', (string) $d->{$label}];
        }
        $out = [];
        foreach ($rows as $key => $row) {
            $d = $stored->get($key);
            $out[] = array_merge($row, $d ? [$this->amount($d->oem_share), $this->amount($d->dealer_share), $this->amount($d->total_discount)] : ['', '', '']);
        }

        return $out;
    }

    // ------------------------------------------------------------------ import

    /**
     * @param  list<string>  $groups
     * @param  (callable(array<string, mixed>): void)|null  $onProgress
     * @return array{sheets: array<string, array<string, int>>, issues: list<array{sheet: string, row: int, reason: string}>}
     */
    public function import(string $path, array $groups, string $wefDate, ?callable $onProgress = null): array
    {
        $result = ['sheets' => [], 'issues' => []];
        $titles = [];
        foreach ($this->reader->sheetNames($path) as $title) {
            if (($group = self::groupOf($title)) !== null && ! isset($titles[$group])) {
                $titles[$group] = $title;
            }
        }
        foreach (array_keys(self::SHEETS) as $group) {
            if (! in_array($group, $groups, true)) {
                continue;
            }
            if (! isset($titles[$group])) {
                $this->issue($result, self::SHEETS[$group], 0, 'Sheet not in the workbook — this group was left unchanged.');

                continue;
            }
            $onProgress && $onProgress(['sheet' => $titles[$group]]);
            $result['sheets'][$group] = $this->importGroup($path, $titles[$group], $group, $wefDate, $result);
        }
        Log::info('[Pricing] add-ons import', ['wef' => $wefDate, 'sheets' => $result['sheets']]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, int>
     */
    private function importGroup(string $path, string $title, string $group, string $wefDate, array &$result): array
    {
        $stats = ['rows' => 0, 'written' => 0, 'blank' => 0, 'duplicates' => 0, 'rejected' => 0, 'expired' => 0];
        $header = $this->reader->header($path, $title, $group);
        if ($header['row'] === null) {
            $this->issue($result, $title, 0, 'Header row not found — this group was left unchanged.');

            return $stats;
        }
        $records = [];
        $byScope = [];
        foreach ($this->reader->rows($path, $title, $header['row'] + 1) as $rowNo => $cells) {
            $stats['rows']++;
            try {
                $parsed = $this->parse($group, $cells, $header['map'], $wefDate);
            } catch (\InvalidArgumentException $e) {
                $stats['rejected']++;
                $this->issue($result, $title, $rowNo, $e->getMessage());

                continue;
            }
            if ($parsed === []) {
                $stats['blank']++;

                continue;
            }
            $byScope[$this->scopeKey($group, $parsed[0])][] = [$rowNo, $parsed];
        }
        // the same scope twice: identical rows count once, different ones are both rejected (as for prices, DEC-076)
        foreach ($byScope as $rows) {
            $first = $rows[0];
            $conflict = count(array_unique(array_map(fn (array $r) => serialize($r[1]), $rows))) > 1;
            if ($conflict) {
                $stats['rejected'] += count($rows);
                foreach ($rows as [$rowNo]) {
                    $this->issue($result, $title, $rowNo, 'The same scope appears on rows '.implode(', ', array_column($rows, 0)).' with different amounts — fix the sheet; nothing written for it.');
                }

                continue;
            }
            $stats['duplicates'] += count($rows) - 1;
            foreach ($first[1] as $record) {
                $records[] = [$first[0], $record];
            }
        }

        // expire + insert as one unit: a group is replaced whole or not at all
        DB::transaction(function () use ($group, $wefDate, $records, $title, &$stats, &$result) {
            $stats['expired'] = match ($group) {
                'DEALER_CHARGES' => $this->charges->expireActive($wefDate),
                'RSA', 'SHIELD' => $this->addons->expireActive($wefDate, ['addon_type' => $group]),
                default => $this->discounts->expireActive($wefDate, ['discount_type' => $group]),
            };
            foreach ($records as [$rowNo, $record]) {
                try {
                    $this->write($group, $record);
                    $stats['written']++;
                } catch (ValidationException $e) {
                    $stats['rejected']++;
                    $this->issue($result, $title, $rowNo, implode(' ', array_merge(...array_values($e->errors()))));
                }
            }
        });

        return $stats;
    }

    /** @param array<string, mixed> $record */
    private function write(string $group, array $record): void
    {
        $payload = $record;
        if ($group === 'DEALER_CHARGES') {
            $this->charges->create($record);

            return;
        }
        if ($group === 'RSA' || $group === 'SHIELD') {
            $row = $this->addons->create($record);
            AddonHistory::query()->create(['addon_id' => $row->id, 'model_code' => $row->model_code, 'addon_type' => $group, 'payload' => $payload, 'action' => 'insert']);

            return;
        }
        $row = $this->discounts->create($record);
        DiscountHistory::query()->create(['discount_id' => $row->id, 'model_code' => $row->model_code, 'payload' => $payload, 'action' => 'insert']);
    }

    /**
     * One sheet row → the rows to write ([] = a blank row, no rule).
     *
     * @param  list<mixed>  $cells
     * @param  array<string, int>  $map
     * @return list<array<string, mixed>>
     */
    private function parse(string $group, array $cells, array $map, string $wefDate): array
    {
        $text = fn (string $field) => isset($map[$field]) ? PricingWorkbookReader::text($cells[$map[$field]] ?? '') : '';
        $num = fn (string $field) => isset($map[$field]) ? PricingWorkbookReader::number($cells[$map[$field]] ?? null) : null;
        $base = ['is_active' => true, 'wef_date' => $wefDate];

        switch ($group) {
            case 'DEALER_CHARGES':
                $amounts = [];
                foreach (self::CHARGE_COLUMNS as $field => $column) {
                    if (($n = $num($field)) !== null) {
                        $amounts[$column] = $n;
                    }
                }
                if ($amounts === [] || $text('segment') === '') {
                    return [];
                }

                return [$base + $amounts + ['segment' => $text('segment'), 'permit' => $text('permit') ?: null, 'model_code' => $this->modelCode($text('model'))]];

            case 'RSA':
                $out = [];
                foreach ([1, 2, 3, 4, 5] as $years) {
                    if (($amount = $num('std_plus_'.$years)) === null) {
                        continue;
                    }
                    $out[] = $base + ['addon_type' => 'RSA', 'segment' => $text('segment') ?: null, 'model_code' => $this->modelCode($text('model')),
                        'name' => $text('std_coverage') ?: null, 'scheme_name' => 'Std + '.$years.($years === 1 ? ' Year' : ' Years'),
                        'tenure_years' => $years, 'amount' => $amount, 'is_default' => $out === []];
                }

                return $out;

            case 'SHIELD':
                $out = [];
                foreach ([1, 2] as $i) {
                    $amount = $num("scheme_{$i}_amt");
                    $name = $text("scheme_{$i}_name");
                    if ($amount === null && $name === '') {
                        continue;
                    }
                    $out[] = $base + ['addon_type' => 'SHIELD', 'model_code' => $this->modelCode($text('oem_model')), 'variant_code' => $this->anyToNull($text('oem_variant')),
                        'shield_pack' => $this->anyToNull($text('shield_pack')), 'transmission' => $this->anyToNull($text('transmission')), 'fuel' => $this->anyToNull($text('fuel')),
                        'name' => $text('standard_warranty') ?: null, 'scheme_name' => $name ?: 'Shield Scheme '.$i, 'tenure_years' => $i,
                        'amount' => $amount ?? 0, 'is_default' => $i === 1];
                }

                return $out;

            default: // EXCHANGE / LOYALTY (scheme) / CORPORATE (category)
                [$oem, $dealer, $total] = [$num('oem_share'), $num('dealer_share'), $num('total')];
                if ($oem === null && $dealer === null && $total === null) {
                    return [];
                }
                $option = $text($group === 'CORPORATE' ? 'category' : 'scheme_type');

                return [$base + ['discount_type' => $group, 'model_code' => $this->modelCode($text('model')), 'variant_code' => $this->anyToNull($text('variant')),
                    'scheme_name' => $group === 'CORPORATE' ? null : $option, 'category' => $group === 'CORPORATE' ? $option : null,
                    'discount_category' => $group === 'CORPORATE' ? $option : $group, 'name' => $option ?: $group,
                    'oem_share' => $oem ?? 0, 'dealer_share' => $dealer ?? 0, 'total_discount' => $total, 'is_conditional' => true]];
        }
    }

    /** @param array<string, mixed> $record the row's scope (what a later row with the same scope would replace) */
    private function scopeKey(string $group, array $record): string
    {
        $fields = match ($group) {
            'DEALER_CHARGES' => ['segment', 'permit', 'model_code'],
            'RSA' => ['segment', 'model_code'],
            'SHIELD' => ['model_code', 'variant_code', 'shield_pack', 'transmission', 'fuel'],
            'EXCHANGE', 'LOYALTY' => ['model_code', 'variant_code', 'scheme_name'],
            default => ['model_code', 'variant_code', 'category'],
        };

        return strtoupper(implode('|', array_map(fn (string $f) => trim((string) ($record[$f] ?? '')), $fields)));
    }

    /** "Any" / blank → ANY; otherwise the model's code (unknown → the row is rejected). */
    private function modelCode(string $name): string
    {
        if ($this->anyToNull($name) === null) {
            return 'ANY';
        }
        $model = $this->vehicles->findModel($name);
        if (! $model) {
            throw new \InvalidArgumentException("Model \"{$name}\" is not in the vehicle master.");
        }

        return $model->code;
    }

    private function anyToNull(string $value): ?string
    {
        return in_array(strtoupper(trim($value)), ['', 'ANY', 'ALL', '*'], true) ? null : $value;
    }

    /** @param array<string, mixed> $result */
    private function issue(array &$result, string $sheet, int $row, string $reason): void
    {
        if (count($result['issues']) < self::MAX_ISSUES) {
            $result['issues'][] = ['sheet' => $sheet, 'row' => $row, 'reason' => $reason];
        }
    }
}

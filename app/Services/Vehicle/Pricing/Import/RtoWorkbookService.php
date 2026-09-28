<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Import;

use App\Models\Vehicle\Pricing\RtoRule;
use App\Services\Vehicle\Pricing\Rules\RtoRuleService;
use App\Services\Vehicle\Pricing\Rules\RuleFormula;
use App\Services\Vehicle\Pricing\Rules\RuleRange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Step 6 — the standalone RTO workbook (DEC-073 / DEC-078): one "RTO" sheet in the reference columns.
 *
 *  presence()                 live RTO rules
 *  export($path)              the live rules, exactly as written (ranges, tax basis, tax slab formula, surcharge formula)
 *  import($path, $wef)        one transaction: expire the live rules at the WEF, insert the sheet's rows through
 *                             RtoRuleService. Ranges and formulas are checked (RuleRange / RuleFormula) — a row that
 *                             does not parse is rejected, an inverted range is imported with a warning.
 * Run import() inside PricingSessionService::record() so Discard restores the previous rules.
 */
class RtoWorkbookService
{
    public const SHEET = 'RTO';

    /** field (sheet_headers RTO_RULES) => column label, in the reference order */
    public const COLUMNS = [
        'permit' => 'Permit', 'wheels' => 'Wheels', 'reg_type' => 'Reg Type', 'body_type' => 'Body Type', 'gvw_range' => 'GVW',
        'seater' => 'Seater', 'fuel_type' => 'Fuel', 'cc_range' => 'CC', 'assessable_range' => 'Assesable Value',
        'trc' => 'Outside State - TRC Only', 'tax_basis' => 'Tax Factor', 'tax_slab' => 'Tax Slab', 'surcharge' => 'Surcharge',
        'hypothecation' => 'Hypothecation', 'green_tax' => 'Green Tax', 'registration_fee' => 'Registration Fee',
        'duplicate_tax_card' => 'Duplicate Tax Card', 'fitness' => 'Fitness', 'penalty' => 'Penalty', 'total' => 'Total Amount',
    ];

    private const RANGES = ['gvw_range' => 'GVW', 'seater' => 'Seater', 'cc_range' => 'CC', 'assessable_range' => 'Assesable Value'];

    private const AMOUNTS = ['hypothecation', 'green_tax', 'registration_fee', 'duplicate_tax_card', 'fitness', 'penalty'];

    public const MAX_ISSUES = 2000;

    public function __construct(private readonly PricingWorkbookReader $reader, private readonly RtoRuleService $rules) {}

    public function presence(): int
    {
        return RtoRule::query()->where('is_active', true)->count();
    }

    /** @return int rows written */
    public function export(string $path): int
    {
        $rows = RtoRule::query()->where('is_active', true)->orderBy('id')->get()->map(fn (RtoRule $r) => [
            $r->permit, $r->wheels ?? 'ANY', $r->reg_type, $r->body_type, $r->gvw_range, $r->seater, $r->fuel_type, $r->cc_range, $r->assessable_range,
            (float) $r->rto_tape, $r->tax_basis, is_numeric($r->tax_slab) ? (float) $r->tax_slab : $r->tax_slab,
            $r->surcharge_formula ?? (float) $r->surcharge, (float) $r->hypothecation, (float) $r->green_tax, (float) $r->registration_fee,
            (float) $r->duplicate_tax_card, (float) $r->fitness, (float) $r->penalty, $r->extra_json['total'] ?? 'Sum of All',
        ])->all();

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle(self::SHEET);
        $sheet->fromArray(array_merge([array_values(self::COLUMNS)], $rows), null, 'A1', true);
        $sheet->getStyle('A1:T1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return count($rows);
    }

    /**
     * @param  (callable(array<string, mixed>): void)|null  $onProgress
     * @return array{rows: int, written: int, blank: int, duplicates: int, rejected: int, expired: int, issues: list<array{sheet: string, row: int, reason: string}>}
     */
    public function import(string $path, string $wefDate, ?callable $onProgress = null): array
    {
        $result = ['rows' => 0, 'written' => 0, 'blank' => 0, 'duplicates' => 0, 'rejected' => 0, 'expired' => 0, 'issues' => []];
        $titles = $this->reader->sheetNames($path);
        $sheet = collect($titles)->first(fn (string $t) => strtoupper(trim($t)) === self::SHEET) ?? ($titles[0] ?? self::SHEET);
        $header = $this->reader->header($path, $sheet, 'RTO_RULES');
        if ($header['row'] === null || ! isset($header['map']['permit'])) {
            throw ValidationException::withMessages(['file' => 'No RTO sheet with a "Permit" column found — use the exported RTO workbook.']);
        }
        $map = $header['map'];

        $byScope = [];
        foreach ($this->reader->rows($path, $sheet, $header['row'] + 1) as $rowNo => $cells) {
            $result['rows']++;
            try {
                $record = $this->parse($cells, $map, $wefDate, $rowNo, $result);
            } catch (\InvalidArgumentException $e) {
                $result['rejected']++;
                $this->issue($result, $sheet, $rowNo, $e->getMessage());

                continue;
            }
            if ($record === null) {
                $result['blank']++;

                continue;
            }
            $key = strtoupper(implode('|', array_map(fn ($f) => trim((string) ($record[$f] ?? '')), ['permit', 'wheels', 'reg_type', 'body_type', 'gvw_range', 'seater', 'fuel_type', 'cc_range', 'assessable_range'])));
            $byScope[$key][] = [$rowNo, $record];
        }
        $onProgress && $onProgress(['message' => 'Writing '.count($byScope).' RTO rule(s)…']);

        DB::transaction(function () use ($byScope, $wefDate, $sheet, &$result) {
            $result['expired'] = $this->rules->expireActive($wefDate);
            foreach ($byScope as $rows) {
                if (count(array_unique(array_map(fn ($r) => serialize($r[1]), $rows))) > 1) {
                    $result['rejected'] += count($rows);
                    foreach ($rows as [$rowNo]) {
                        $this->issue($result, $sheet, $rowNo, 'The same scope appears on rows '.implode(', ', array_column($rows, 0)).' with different values — fix the sheet; nothing written for it.');
                    }

                    continue;
                }
                $result['duplicates'] += count($rows) - 1;
                try {
                    $this->rules->create($rows[0][1]);
                    $result['written']++;
                } catch (ValidationException $e) {
                    $result['rejected']++;
                    $this->issue($result, $sheet, $rows[0][0], implode(' ', array_merge(...array_values($e->errors()))));
                }
            }
        });
        Log::info('[Pricing] RTO rules import', array_diff_key($result, ['issues' => 1]));

        return $result;
    }

    /**
     * @param  list<mixed>  $cells
     * @param  array<string, int>  $map
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>|null null = a blank row
     */
    private function parse(array $cells, array $map, string $wefDate, int $rowNo, array &$result): ?array
    {
        $raw = fn (string $f) => isset($map[$f]) ? $cells[$map[$f]] ?? null : null;
        $text = fn (string $f) => PricingWorkbookReader::text($raw($f) ?? '');
        if ($text('permit') === '') {
            return null;
        }
        foreach (self::RANGES as $field => $label) {
            if (RuleRange::parse($text($field))->isInverted()) {
                $this->issue($result, self::SHEET, $rowNo, "{$label} \"{$text($field)}\" runs backwards — it can never match (imported as written).");
            }
        }
        $slab = $raw('tax_slab');
        $taxFactor = ($slab === null || $slab === '') ? 0.0 : RuleFormula::evaluate($slab);
        $surcharge = $text('surcharge');
        if ($surcharge !== '') {
            RuleFormula::variables($surcharge);
        }
        $record = [
            'permit' => $text('permit'), 'wheels' => $text('wheels') ?: null, 'reg_type' => $text('reg_type') ?: null,
            'body_type' => $text('body_type') ?: null, 'gvw_range' => $text('gvw_range') ?: null, 'seater' => $text('seater') ?: null,
            'fuel_type' => $text('fuel_type') ?: null, 'cc_range' => $text('cc_range') ?: null, 'assessable_range' => $text('assessable_range') ?: null,
            'tax_basis' => $text('tax_basis') ?: null, 'tax_slab' => $slab === null || $slab === '' ? null : PricingWorkbookReader::text($slab),
            'tax_factor' => round($taxFactor, 6), 'surcharge' => $surcharge ?: null, 'surcharge_formula' => $surcharge ?: null,
            'rto_tape' => PricingWorkbookReader::number($raw('trc')), 'extra_json' => ['total' => $text('total') ?: 'Sum of All'],
            'is_active' => true, 'wef_date' => $wefDate,
        ];
        foreach (self::AMOUNTS as $field) {
            $record[$field] = PricingWorkbookReader::number($raw($field));
        }

        return array_filter($record, fn ($v) => $v !== null);
    }

    /** @param array<string, mixed> $result */
    private function issue(array &$result, string $sheet, int $row, string $reason): void
    {
        if (count($result['issues']) < self::MAX_ISSUES) {
            $result['issues'][] = ['sheet' => $sheet, 'row' => $row, 'reason' => $reason];
        }
    }
}

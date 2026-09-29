<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Import;

use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\Variant;
use App\Services\KeywordValueService;
use App\Services\Vehicle\VehicleCompleteness;
use App\Services\Vehicle\VehicleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Step 3 — Vehicle Info round-trip (DEC-073).
 *
 *  export($path)            every vehicle in the master (complete, incomplete, inactive, discontinued), sorted
 *                           Segment → OEM Model → code, in the reference "Vehicle Info" layout + a Missing Fields column;
 *                           lookups (Fuel, Permit, Body Make / Type) written as their key-value codes; every lookup
 *                           column is a dropdown fed from the masters (hidden "Lists" sheet; Sub Segment follows the
 *                           row's Segment; numbers range-checked), so an edited value always relates to existing data
 *                           (owner request 30-09)
 *  import($path, $onProgress)  fills the masters through VehicleService::applyVehicleInfo() (entity services; the
 *                           masters must exist — an unknown segment / sub-segment / lookup value rejects the row) and
 *                           reports completed / still incomplete / rejected per row. It never creates vehicles — they
 *                           come only from the price lists (Detect). Run it inside PricingSessionService::record().
 */
class VehicleInfoWorkbookService
{
    public const SHEET = 'Vehicle Info';

    /** field code (sheet_headers VEHICLE_INFO) => column label, in the reference order */
    public const COLUMNS = [
        'model_code' => 'Model Code', 'oem_model' => 'OEM Model', 'oem_variant' => 'OEM Variant',
        'segment' => 'Segment', 'sub_segment' => 'Sub Segment', 'fuel' => 'Fuel', 'seating' => 'Seating',
        'wheels' => 'Wheels', 'transmission' => 'Transmission', 'drivetrain' => 'Drivetrain',
        'body_make' => 'Body Make', 'body_type' => 'Body Type', 'cc' => 'CC', 'motor' => 'Motor', 'gvw' => 'GVW',
        'gst_pct' => 'GST%', 'permit' => 'Permit', 'taxi_price' => 'Taxi Price', 'custom_model' => 'Custom Model',
        'custom_variant' => 'Custom Variant', 'display_name' => 'Display Name', 'colour_name' => 'Colour Name',
        'status' => 'Status', 'shield_pack' => 'Shield Pack', 'missing' => 'Missing Fields',
    ];

    /** row issues kept in the session stats (the rest are counted) */
    public const MAX_ISSUES = 5000;

    public function __construct(
        private readonly PricingWorkbookReader $reader,
        private readonly VehicleService $vehicles,
        private readonly VehicleCompleteness $completeness,
    ) {}

    /** @return array{rows: int, incomplete: int} */
    public function export(string $path): array
    {
        $lookups = $this->lookupCodes();
        $rows = [];
        $incomplete = 0;
        Variant::query()->with('vehicleModel:id,code,name,oem_name')->orderBy('id')
            ->chunkById(500, function ($variants) use (&$rows, &$incomplete, $lookups) {
                foreach ($variants as $v) {
                    $missing = $this->completeness->missingLabels($v);
                    $incomplete += $missing === [] ? 0 : 1;
                    $rows[] = $this->exportRow($v, $missing, $lookups);
                }
            });
        usort($rows, fn (array $a, array $b) => [$a['segment'], $a['oem_model'], $a['model_code']] <=> [$b['segment'], $b['oem_model'], $b['model_code']]);

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle(self::SHEET);
        $sheet->fromArray([array_values(self::COLUMNS)]);
        $sheet->fromArray(array_map(fn (array $r) => array_values(array_merge(array_fill_keys(array_keys(self::COLUMNS), ''), $r)), $rows), null, 'A2', true);

        $last = Coordinate::stringFromColumnIndex(count(self::COLUMNS));
        $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true);
        $sheet->freezePane('B2');
        $sheet->setAutoFilter("A1:{$last}".(count($rows) + 1));
        foreach ($rows as $i => $r) {
            if ($r['missing'] !== '') {
                $sheet->getStyle('A'.($i + 2).":{$last}".($i + 2))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
            }
        }
        $this->addDropdowns($book, $sheet, count($rows));

        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return ['rows' => count($rows), 'incomplete' => $incomplete];
    }

    /**
     * @param  (callable(array<string, int>): void)|null  $onProgress
     * @return array{rows: int, completed: int, newly_completed: int, incomplete: int, rejected: int, unknown: int, issues: list<array{row: int, code: string, result: string, reason: string}>}
     */
    public function import(string $path, ?callable $onProgress = null): array
    {
        $stats = ['rows' => 0, 'completed' => 0, 'newly_completed' => 0, 'incomplete' => 0, 'rejected' => 0, 'unknown' => 0, 'issues' => []];
        $sheet = in_array(self::SHEET, $this->reader->sheetNames($path), true) ? self::SHEET : ($this->reader->sheetNames($path)[0] ?? self::SHEET);
        $header = $this->reader->header($path, $sheet, 'VEHICLE_INFO');
        if ($header['row'] === null || ! isset($header['map']['model_code'])) {
            throw ValidationException::withMessages(['file' => 'No "Model Code" column found — use the exported Vehicle Info sheet.']);
        }
        $map = $header['map'];
        $seen = [];
        $batch = [];

        foreach ($this->reader->rows($path, $sheet, $header['row'] + 1) as $rowNo => $cells) {
            $code = PricingWorkbookReader::code($cells[$map['model_code']] ?? '');
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $stats['rows']++;
            $batch[] = [$rowNo, $code, $this->mapRow($cells, $map)];
            if (count($batch) >= 100) {
                $this->importBatch($batch, $stats);
                $batch = [];
                $onProgress && $onProgress(array_diff_key($stats, ['issues' => 1]));
            }
        }
        $this->importBatch($batch, $stats);
        Log::info('[Pricing] vehicle info import', array_diff_key($stats, ['issues' => 1]));

        return $stats;
    }

    /**
     * One commit per batch: row-by-row autocommit made each row cost ~110 ms (vs ~15 ms).
     *
     * @param  list<array{0: int, 1: string, 2: array<string, string>}>  $batch
     * @param  array<string, mixed>  $stats
     */
    private function importBatch(array $batch, array &$stats): void
    {
        if ($batch === []) {
            return;
        }
        DB::transaction(function () use ($batch, &$stats) {
            foreach ($batch as [$rowNo, $code, $row]) {
                $this->importRow($rowNo, $code, $row, $stats);
            }
        });
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $stats
     */
    private function importRow(int $rowNo, string $code, array $row, array &$stats): void
    {
        $variant = Variant::query()->where('code', $code)->first();
        if (! $variant) {
            $stats['unknown']++;
            $this->issue($stats, $rowNo, $code, 'rejected', 'Not in the vehicle master — new vehicles come only from the price lists.');

            return;
        }
        $wasComplete = $this->completeness->isComplete($variant);
        try {
            $result = $this->vehicles->applyVehicleInfo($variant, $row, null, true);
        } catch (ValidationException $e) {
            $stats['rejected']++;
            $this->issue($stats, $rowNo, $code, 'rejected', implode(' ', array_merge(...array_values($e->errors()))));

            return;
        } catch (\Throwable $e) {
            $stats['rejected']++;
            $this->issue($stats, $rowNo, $code, 'rejected', $e->getMessage());

            return;
        }

        if ($result['complete']) {
            $stats['completed']++;
            $stats['newly_completed'] += $wasComplete ? 0 : 1;

            return;
        }
        $stats['incomplete']++;
        $labels = array_map(fn (string $f) => VehicleCompleteness::REQUIRED[$f] ?? VehicleCompleteness::CONDITIONAL[$f] ?? $f, $result['missing']);
        $asked = strtoupper(trim($row['status'] ?? ''));
        $this->issue($stats, $rowNo, $code, 'incomplete', 'Missing: '.implode(', ', $labels).($asked === VehicleService::STATUS_ACTIVE ? ' — kept Incomplete, not Active.' : '.'));
    }

    /** @param array<string, mixed> $stats */
    private function issue(array &$stats, int $rowNo, string $code, string $result, string $reason): void
    {
        if (count($stats['issues']) < self::MAX_ISSUES) {
            $stats['issues'][] = ['row' => $rowNo, 'code' => $code, 'result' => $result, 'reason' => $reason];
        }
    }

    /**
     * Sheet cells → the keys VehicleService::applyVehicleInfo() reads. Blank cells are left out (a blank never clears a
     * stored value); OEM Model / OEM Variant are OEM facts from the price lists and are not imported.
     *
     * @param  list<mixed>  $cells
     * @param  array<string, int>  $map
     * @return array<string, string>
     */
    private function mapRow(array $cells, array $map): array
    {
        $get = fn (string $field) => isset($map[$field]) ? PricingWorkbookReader::text($cells[$map[$field]] ?? '') : '';
        $row = [
            'segment' => $get('segment'), 'sub_segment' => $get('sub_segment'), 'fuel' => $get('fuel'),
            'seating' => $get('seating'), 'wheels' => $get('wheels'), 'transmission' => $get('transmission'),
            'drivetrain' => $get('drivetrain'), 'body_make' => $get('body_make'), 'body_type' => $get('body_type'),
            'cc' => $get('cc'), 'motor' => $get('motor'), 'gvw' => $get('gvw'), 'gst_percent' => $get('gst_pct'),
            'permit' => $get('permit'), 'taxi_price' => $get('taxi_price'), 'custom_model' => $get('custom_model'),
            'custom_variant' => $get('custom_variant'), 'display_name' => $get('display_name'),
            'colour_name' => $get('colour_name'), 'status' => $get('status'), 'shield_pack' => $get('shield_pack'),
        ];

        return array_filter($row, fn (string $v) => $v !== '');
    }

    /**
     * @param  list<string>  $missing
     * @param  array<string, array<int, string>>  $lookups
     * @return array<string, mixed>
     */
    private function exportRow(Variant $v, array $missing, array $lookups): array
    {
        $model = $v->vehicleModel;
        $status = $lookups['VEHICLE_STATUS'][(int) $v->status_id] ?? ($v->is_active ? VehicleService::STATUS_ACTIVE : VehicleService::STATUS_INACTIVE);

        return [
            'model_code' => (string) $v->code,
            'oem_model' => (string) ($model?->oem_name ?: $model?->name ?: $v->model_code),
            'oem_variant' => (string) $v->oem_name,
            'segment' => (string) $v->segment_code,
            'sub_segment' => (string) $v->sub_segment_code,
            'fuel' => $lookups['FUEL_TYPE'][(int) $v->fuel_type_id] ?? '',
            'seating' => $v->seating_capacity ?? '',
            'wheels' => $v->wheels ?? '',
            'transmission' => (string) $v->transmission,
            'drivetrain' => (string) $v->drivetrain,
            'body_make' => $lookups['BODY_MAKE'][(int) $v->body_make_id] ?? '',
            'body_type' => $lookups['BODY_TYPE'][(int) $v->body_type_id] ?? '',
            'cc' => (string) $v->cc_capacity,
            'motor' => (string) $v->motor,
            'gvw' => $v->gvw ?? '',
            'gst_pct' => $v->gst_percent === null ? '' : (float) $v->gst_percent,
            'permit' => $lookups['PERMIT'][(int) $v->permit_id] ?? '',
            'taxi_price' => (string) $v->taxi_price,
            'custom_model' => (string) $model?->name,
            'custom_variant' => (string) $v->custom_name,
            'display_name' => (string) $v->display_name,
            'colour_name' => (string) $v->color,
            'status' => $status,
            'shield_pack' => (string) $v->shield_pack,
            'missing' => implode(', ', $missing),
        ];
    }

    /**
     * Master-fed dropdowns on the Vehicle Info sheet (owner request 30-09). A hidden "Lists" sheet holds the codes; each
     * lookup column gets a list validation (stop on anything else), Sub Segment follows the row's Segment through a
     * named range per segment (`SUB_<CODE>`), and the numeric columns are range-checked. Codes are what the import reads.
     */
    private function addDropdowns(Spreadsheet $book, Worksheet $sheet, int $rowCount): void
    {
        $lists = $book->createSheet()->setTitle('Lists');
        $segments = Segment::query()->where('is_active', true)->orderBy('code')->pluck('code')->map(fn ($c) => (string) $c)->all();
        $subSegments = SubSegment::query()->where('is_active', true)->orderBy('code')->get(['segment_code', 'code'])
            ->groupBy('segment_code')->map(fn ($rows) => $rows->pluck('code')->map(fn ($c) => (string) $c)->all())->all();
        $columns = [
            'segment' => $segments,
            'fuel' => array_keys($this->vehicles->keywordOptions('FUEL_TYPE')),
            'transmission' => array_keys($this->vehicles->keywordOptions('TRANSMISSION')),
            'drivetrain' => array_keys($this->vehicles->keywordOptions('DRIVETRAIN')),
            'body_make' => array_keys($this->vehicles->keywordOptions('BODY_MAKE')),
            'body_type' => array_keys($this->vehicles->keywordOptions('BODY_TYPE')),
            'permit' => array_keys($this->vehicles->keywordOptions('PERMIT')),
            'taxi_price' => ['YES', 'NO'],
            'status' => [VehicleService::STATUS_ACTIVE, VehicleService::STATUS_INACTIVE, VehicleService::STATUS_DISCONTINUED, VehicleService::STATUS_INCOMPLETE],
        ];
        $lastRow = max(2, $rowCount + 1);
        $keys = array_keys(self::COLUMNS);
        $col = fn (string $field) => Coordinate::stringFromColumnIndex((int) array_search($field, $keys, true) + 1);
        $range = fn (string $field) => $col($field).'2:'.$col($field).$lastRow;

        $listCol = 0;
        $writeList = function (string $title, array $values, string $name) use (&$listCol, $lists, $book): void {
            $listCol++;
            $letter = Coordinate::stringFromColumnIndex($listCol);
            $lists->setCellValue($letter.'1', $title);
            foreach (array_values($values) as $i => $value) {
                $lists->setCellValueExplicit($letter.($i + 2), $value, DataType::TYPE_STRING);
            }
            $end = max(2, count($values) + 1);
            $book->addNamedRange(new NamedRange($name, $lists, '$'.$letter.'$2:$'.$letter.'$'.$end));
        };

        foreach ($columns as $field => $values) {
            $name = 'LST_'.strtoupper($field);
            $writeList(self::COLUMNS[$field], $values, $name);
            $this->listValidation($sheet, $range($field), '='.$name, self::COLUMNS[$field]);
        }

        // Sub Segment: one named range per segment, picked by the row's Segment
        foreach ($segments as $segment) {
            $writeList('Sub Segment · '.$segment, $subSegments[$segment] ?? [], $this->subSegmentRange($segment));
        }
        $this->listValidation($sheet, $range('sub_segment'),
            '=INDIRECT("SUB_"&SUBSTITUTE(SUBSTITUTE(SUBSTITUTE($'.$col('segment').'2,"-","_")," ","_"),".","_"))', self::COLUMNS['sub_segment']);

        foreach (['seating' => [1, 100], 'wheels' => [1, 30]] as $field => [$min, $max]) {
            $this->numberValidation($sheet, $range($field), DataValidation::TYPE_WHOLE, $min, $max, self::COLUMNS[$field]);
        }
        $this->numberValidation($sheet, $range('gst_pct'), DataValidation::TYPE_DECIMAL, 0, 100, self::COLUMNS['gst_pct']);

        $lists->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
        $book->setActiveSheetIndex(0);
    }

    /** `SUB_<segment>`: a valid Excel name (letters, digits, underscores) — the INDIRECT formula builds the same. */
    private function subSegmentRange(string $segment): string
    {
        return 'SUB_'.str_replace(['-', ' ', '.'], '_', strtoupper($segment));
    }

    private function listValidation(Worksheet $sheet, string $range, string $formula, string $label): void
    {
        $v = new DataValidation;
        $v->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)->setAllowBlank(true)
            ->setShowDropDown(true)->setShowErrorMessage(true)->setShowInputMessage(true)
            ->setErrorTitle($label.': pick from the list')
            ->setError('Choose a '.$label.' from the dropdown (the masters). New values are added in the masters first.')
            ->setPromptTitle($label)->setPrompt('Pick from the list')
            ->setFormula1($formula);
        $sheet->setDataValidation($range, $v);
    }

    private function numberValidation(Worksheet $sheet, string $range, string $type, int|float $min, int|float $max, string $label): void
    {
        $v = new DataValidation;
        $v->setType($type)->setOperator(DataValidation::OPERATOR_BETWEEN)->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)->setShowErrorMessage(true)
            ->setErrorTitle($label.': out of range')->setError($label.' must be a number from '.$min.' to '.$max.'.')
            ->setFormula1((string) $min)->setFormula2((string) $max);
        $sheet->setDataValidation($range, $v);
    }

    /** @return array<string, array<int, string>> keyword => [key-value id => code] */
    private function lookupCodes(): array
    {
        $out = [];
        foreach (['FUEL_TYPE', 'PERMIT', 'BODY_MAKE', 'BODY_TYPE', 'VEHICLE_STATUS'] as $keyword) {
            foreach (array_keys(KeywordValueService::getEnum($keyword, false)) as $code) {
                $id = KeywordValueService::getValueId($keyword, (string) $code, false);
                if ($id !== null) {
                    $out[$keyword][$id] = strtoupper((string) $code);
                }
            }
        }

        return $out;
    }
}

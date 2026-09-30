<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Content;

use App\Models\Vehicle\FeatureItem;
use App\Models\Vehicle\ModelSpec;
use App\Models\Vehicle\SpecItem;
use App\Models\Vehicle\TrimFeature;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Specifications / features workbooks (DEC-092 Phase 3).
 *
 * Our format (export → edit → import):
 *  - specifications: one sheet per segment; columns Category | Specification | Unit | Item code | one column per model
 *    headed "CODE · Name";
 *  - features: one sheet per model; columns Feature group | Feature | Item code | one column per trim headed
 *    "VARIANT-CODE · Name".
 * The OEM sample format (the owner's Vehicle_Specifications.xlsx / Vehicle-Features.xlsx) is read too: rows
 * Head / SubHead (or Head Group / Head), columns named by model / variant name — matched to our models and trims by
 * normalised name (a sample trim name may be the start of ours, e.g. "… - R" vs "… - R - REFRESH"); every column that
 * does not match is reported.
 *
 * Import rules (owner, DEC-092): unknown items are added (reported); a blank cell keeps what is stored; "-", "-NA-", "_"
 * mean not applicable (specifications) / No (features). Values go through ModelSpecService / TrimFeatureService.
 */
final class VehicleContentWorkbookService
{
    public function __construct(
        private readonly ModelSpecService $specs,
        private readonly TrimFeatureService $features,
        private readonly SpecItemService $specItems,
        private readonly FeatureItemService $featureItems,
    ) {}

    /** @return array{sheets: int} */
    public function exportSpecs(string $path): array
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        $items = SpecItem::query()->where('is_active', true)->orderBy('category')->orderBy('sort')->orderBy('name')->get();
        $values = ModelSpec::query()->get(['model_code', 'spec_item_code', 'value'])->groupBy('model_code')
            ->map(fn ($rows) => $rows->pluck('value', 'spec_item_code'));

        foreach (VehicleModel::query()->where('is_active', true)->orderBy('segment_code')->orderBy('name')->get(['code', 'name', 'segment_code'])->groupBy('segment_code') as $segment => $models) {
            $sheet = $book->createSheet()->setTitle(mb_substr((string) ($segment ?: 'OTHER'), 0, 31));
            $header = ['Category', 'Specification', 'Unit', 'Item code'];
            foreach ($models as $m) {
                $header[] = $m->code.' · '.$m->name;
            }
            $rows = [];
            foreach ($items as $item) {
                $row = [$item->category, $item->name, $item->unit, $item->code];
                foreach ($models as $m) {
                    $row[] = $values[$m->code][$item->code] ?? null;
                }
                $rows[] = $row;
            }
            $this->writeSheet($sheet, $header, $rows, 4);
        }

        return $this->save($book, $path);
    }

    /** @return array{sheets: int} */
    public function exportFeatures(string $path): array
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        $items = FeatureItem::query()->where('is_active', true)->orderBy('feature_group')->orderBy('sort')->orderBy('name')->get();
        $values = TrimFeature::query()->get(['variant_code', 'feature_item_code', 'value'])->groupBy('variant_code')
            ->map(fn ($rows) => $rows->pluck('value', 'feature_item_code'));
        $trims = Variant::query()->whereNotNull('model_code')->selectRaw('model_code, code, MAX(display_name) as label')
            ->groupBy('model_code', 'code')->orderBy('label')->get()->groupBy('model_code');

        foreach (VehicleModel::query()->where('is_active', true)->orderBy('name')->get(['code', 'name']) as $model) {
            $own = $trims->get($model->code);
            if ($own === null) {
                continue;
            }
            $sheet = $book->createSheet()->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '-', (string) $model->code), 0, 31));
            $header = ['Feature group', 'Feature', 'Item code'];
            foreach ($own as $t) {
                $header[] = $t->code.' · '.$t->getAttribute('label');
            }
            $rows = [];
            foreach ($items as $item) {
                $row = [$item->feature_group, $item->name, $item->code];
                foreach ($own as $t) {
                    $row[] = $values[$t->code][$item->code] ?? null;
                }
                $rows[] = $row;
            }
            $this->writeSheet($sheet, $header, $rows, 3);
        }

        return $this->save($book, $path);
    }

    /**
     * Import a specifications workbook (ours or the OEM sample).
     *
     * @return array{format: string, values: int, items_added: list<string>, unmatched: list<string>}
     */
    public function importSpecs(string $path): array
    {
        $report = ['format' => '', 'values' => 0, 'items_added' => [], 'unmatched' => []];
        $models = $this->modelIndex();

        foreach ($this->sheets($path) as $title => $rows) {
            $header = array_map(fn ($v) => trim((string) $v), $rows[0] ?? []);
            $ours = strcasecmp($header[3] ?? '', 'Item code') === 0;
            $report['format'] = $ours ? 'workbook' : 'sample';
            $firstValue = $ours ? 4 : 2;
            $columns = $this->matchColumns($header, $firstValue, fn (string $h) => $ours ? $this->codeFromHeader($h, $models['codes']) : $this->modelByName($h, $models), "{$title}: ", $report);

            foreach (array_slice($rows, 1) as $row) {
                $category = trim((string) ($row[0] ?? ''));
                $name = trim((string) ($row[1] ?? ''));
                if ($category === '' && $name === '') {
                    continue;
                }
                $itemCode = $ours && trim((string) ($row[3] ?? '')) !== '' ? strtoupper(trim((string) $row[3])) : null;
                $item = $this->specItem($itemCode, $category, $name === '' ? $category : $name, $ours ? trim((string) ($row[2] ?? '')) : null, $report);
                foreach ($columns as $index => $modelCode) {
                    $raw = $row[$index] ?? null;
                    if (trim((string) $raw) === '') {
                        continue;   // blank keeps
                    }
                    $value = $this->notApplicable((string) $raw) ? ModelSpecService::NOT_APPLICABLE : trim((string) $raw);
                    $this->specs->upsert(['model_code' => $modelCode, 'spec_item_code' => $item->code, 'value' => $value]);
                    $report['values']++;
                }
            }
        }

        return $report;
    }

    /**
     * Import a features workbook (ours or the OEM sample).
     *
     * @return array{format: string, values: int, items_added: list<string>, unmatched: list<string>}
     */
    public function importFeatures(string $path): array
    {
        $report = ['format' => '', 'values' => 0, 'items_added' => [], 'unmatched' => []];
        $models = $this->modelIndex();

        foreach ($this->sheets($path) as $title => $rows) {
            $header = array_map(fn ($v) => trim((string) $v), $rows[0] ?? []);
            $ours = strcasecmp($header[2] ?? '', 'Item code') === 0;
            $report['format'] = $ours ? 'workbook' : 'sample';
            $modelCode = $ours ? ($models['codes'][strtoupper(trim((string) $title))] ?? null) : $this->modelByName((string) $title, $models);
            if ($modelCode === null && ! $ours) {
                $report['unmatched'][] = "sheet \"{$title}\" (no model with that name)";

                continue;
            }
            $trims = $this->trimIndex($modelCode);
            $firstValue = $ours ? 3 : 2;
            $columns = $this->matchColumns($header, $firstValue, fn (string $h) => $ours ? $this->codeFromHeader($h, $trims['codes']) : $this->trimByName($h, $trims), "{$title}: ", $report);

            foreach (array_slice($rows, 1) as $row) {
                $group = trim((string) ($row[0] ?? ''));
                $name = trim((string) ($row[1] ?? ''));
                if ($name === '') {
                    continue;
                }
                $group = $group === '' || strcasecmp($group, 'undefined') === 0 ? 'General' : $group;
                $itemCode = $ours && trim((string) ($row[2] ?? '')) !== '' ? strtoupper(trim((string) $row[2])) : null;
                $item = $this->featureItem($itemCode, $group, $name, $report);
                foreach ($columns as $index => $variantCode) {
                    $raw = $row[$index] ?? null;
                    if (trim((string) $raw) === '') {
                        continue;   // blank keeps
                    }
                    $this->features->upsert(['variant_code' => $variantCode, 'feature_item_code' => $item->code, 'value' => (string) $raw]);
                    $report['values']++;
                }
            }
        }

        return $report;
    }

    /**
     * @param  list<string>  $header
     * @param  callable(string): ?string  $resolve
     * @param  array{unmatched: list<string>}  $report
     * @return array<int, string> column index => model / variant code
     */
    private function matchColumns(array $header, int $from, callable $resolve, string $prefix, array &$report): array
    {
        $columns = [];
        foreach ($header as $index => $h) {
            if ($index < $from || $h === '') {
                continue;
            }
            $code = $resolve($h);
            $code === null ? $report['unmatched'][] = $prefix.$h : $columns[$index] = $code;
        }

        return $columns;
    }

    /** @param  array{items_added: list<string>}  $report */
    private function specItem(?string $code, string $category, string $name, ?string $unit, array &$report): SpecItem
    {
        $item = $code ? SpecItem::query()->where('code', $code)->first() : null;
        $item ??= SpecItem::query()->whereRaw('UPPER(category) = ?', [mb_strtoupper($category)])->whereRaw('UPPER(name) = ?', [mb_strtoupper($name)])->first();
        if ($item === null) {
            /** @var SpecItem $item */
            $item = $this->specItems->create(['code' => $code, 'category' => $category, 'name' => $name, 'unit' => $unit ?: null]);
            $report['items_added'][] = "{$item->category} → {$item->name}";
        }

        return $item;
    }

    /** @param  array{items_added: list<string>}  $report */
    private function featureItem(?string $code, string $group, string $name, array &$report): FeatureItem
    {
        $item = $code ? FeatureItem::query()->where('code', $code)->first() : null;
        $item ??= FeatureItem::query()->whereRaw('UPPER(feature_group) = ?', [mb_strtoupper($group)])->whereRaw('UPPER(name) = ?', [mb_strtoupper($name)])->first();
        if ($item === null) {
            /** @var FeatureItem $item */
            $item = $this->featureItems->create(['code' => $code, 'feature_group' => $group, 'name' => $name]);
            $report['items_added'][] = "{$item->feature_group} → {$item->name}";
        }

        return $item;
    }

    /** @return array{codes: array<string, string>, names: array<string, string>} CODE => code; normalised name => code */
    private function modelIndex(): array
    {
        $codes = $names = [];
        foreach (VehicleModel::query()->get(['code', 'name', 'oem_name']) as $m) {
            $codes[strtoupper((string) $m->code)] = (string) $m->code;
            foreach ([$m->name, $m->oem_name, $m->code] as $label) {
                $key = self::norm((string) $label);
                if ($key !== '') {
                    $names[$key] ??= (string) $m->code;
                }
            }
        }

        return ['codes' => $codes, 'names' => $names];
    }

    /** @return array{codes: array<string, string>, names: array<string, string>} VARIANT CODE => code; normalised name => code */
    private function trimIndex(?string $modelCode): array
    {
        $codes = $names = [];
        $query = Variant::query()->whereNotNull('model_code');
        if ($modelCode !== null) {
            $query->where('model_code', $modelCode);
        }
        foreach ($query->get(['code', 'display_name', 'custom_name', 'oem_name']) as $v) {
            $codes[strtoupper((string) $v->code)] = (string) $v->code;
            foreach ([$v->display_name, $v->custom_name, $v->oem_name] as $label) {
                $key = self::norm((string) $label);
                if ($key !== '') {
                    $names[$key] ??= (string) $v->code;
                }
            }
        }

        return ['codes' => $codes, 'names' => $names];
    }

    /** @param  array<string, string>  $codes */
    private function codeFromHeader(string $header, array $codes): ?string
    {
        $code = strtoupper(trim(explode('·', $header)[0]));

        return $codes[$code] ?? null;
    }

    /** @param  array{names: array<string, string>}  $index */
    private function modelByName(string $name, array $index): ?string
    {
        return $index['names'][self::norm($name)] ?? null;
    }

    /**
     * A sample trim name: exact normalised match, else the one trim whose name starts with it ("… - R" → "… - R - REFRESH").
     *
     * @param  array{names: array<string, string>}  $index
     */
    private function trimByName(string $name, array $index): ?string
    {
        $key = self::norm($name);
        if ($key === '') {
            return null;
        }
        if (isset($index['names'][$key])) {
            return $index['names'][$key];
        }
        $hits = array_unique(array_values(array_filter($index['names'], fn ($code, $n) => str_starts_with($n, $key), ARRAY_FILTER_USE_BOTH)));

        return count($hits) === 1 ? $hits[0] : null;
    }

    private function notApplicable(string $value): bool
    {
        return (bool) preg_match('/^[-_\s]*(n\.?\s*\/?\s*a\.?)?[-_\s]*$/i', trim($value));
    }

    /** Upper-case letters and digits only ("Scorpio - N" → "SCORPION"; "BOLERO NEO +" → "BOLERONEO"). */
    private static function norm(string $text): string
    {
        return (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($text));
    }

    /** @return array<string, list<list<mixed>>> sheet title => rows */
    private function sheets(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);
        $out = [];
        foreach ($book->getWorksheetIterator() as $sheet) {
            $out[$sheet->getTitle()] = $sheet->toArray(null, false, false);
        }
        $book->disconnectWorksheets();

        return $out;
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<mixed>>  $rows
     */
    private function writeSheet(Worksheet $sheet, array $header, array $rows, int $fixedColumns): void
    {
        $sheet->fromArray([$header]);
        foreach ($rows as $r => $row) {
            foreach ($row as $c => $value) {
                if ($value !== null && $value !== '') {
                    $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($c + 1).($r + 2), (string) $value, DataType::TYPE_STRING);
                }
            }
        }
        $last = Coordinate::stringFromColumnIndex(max(1, count($header)));
        $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true);
        $sheet->freezePane(Coordinate::stringFromColumnIndex($fixedColumns + 1).'2');
        foreach (range(1, count($header)) as $c) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth($c <= $fixedColumns ? 24 : 22);
        }
    }

    /** @return array{sheets: int} */
    private function save(Spreadsheet $book, string $path): array
    {
        if ($book->getSheetCount() === 0) {
            $book->createSheet()->setTitle('Empty');
        }
        $book->setActiveSheetIndex(0);
        $count = $book->getSheetCount();
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return ['sheets' => $count];
    }
}

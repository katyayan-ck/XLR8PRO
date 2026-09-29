<?php

declare(strict_types=1);

namespace App\Services\Org\UsersWorkbook;

use App\Models\Admin\Employee;
use App\Models\Admin\PersonContact;
use App\Models\Admin\UserScope;
use App\Models\User;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\PermissionRegistrar;

/**
 * The users workbook (DEC-089, W10): export every employee user in the owner's fixed layout (`UsersWorkbookColumns`)
 * with master-fed dropdowns, and import it back row by row through `UserRowService`.
 *
 * Workbook: `Users` (the data; single-value master cells are dropdowns, Primary Location follows the row's Primary
 * Branch and Primary Division its Primary Department through named ranges `LOC_<BRANCH>` / `DIV_<DEPT>`; multi-value
 * cells take comma-separated codes), `Lists` (every valid code with its name, `ALL` first and `NONE` last where
 * allowed) and `Instructions`. Aadhaar is exported masked (last 4 digits).
 */
final class UsersWorkbookService
{
    /** Empty rows below the data that still carry the dropdowns (new users). */
    private const SPARE_ROWS = 500;

    private const HEADER_FILL = '1F4E78';

    private const REQUIRED_FILL = 'C00000';

    public function __construct(private readonly UserRowService $rows) {}

    /**
     * Write the workbook to $path; $withUsers = false gives the empty template.
     *
     * @return array{rows: int}
     */
    public function export(string $path, bool $withUsers = true): array
    {
        $data = $withUsers ? $this->userRows() : [];
        $keys = array_keys(UsersWorkbookColumns::HEADERS);

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle(UsersWorkbookColumns::SHEET);
        $sheet->fromArray([array_values(UsersWorkbookColumns::HEADERS)]);
        foreach ($data as $i => $row) {
            foreach ($keys as $c => $key) {
                $value = $row[$key] ?? null;
                if ($value !== null && $value !== '') {
                    $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($c + 1).($i + 2), (string) $value, DataType::TYPE_STRING);
                }
            }
        }

        foreach ($keys as $c => $key) {
            $letter = Coordinate::stringFromColumnIndex($c + 1);
            $required = str_ends_with(UsersWorkbookColumns::HEADERS[$key], '*');
            $style = $sheet->getStyle($letter.'1');
            $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($required ? self::REQUIRED_FILL : self::HEADER_FILL);
            $sheet->getColumnDimension($letter)->setWidth(in_array($key, UsersWorkbookColumns::MULTI, true) ? 28 : 20);
        }
        $sheet->freezePane('C2');
        $sheet->setAutoFilter('A1:'.Coordinate::stringFromColumnIndex(count($keys)).max(2, count($data) + 1));

        $this->addLists($book, $sheet, count($data) + self::SPARE_ROWS);
        $this->addInstructions($book);
        $book->setActiveSheetIndex(0);

        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return ['rows' => count($data)];
    }

    /**
     * Import the `Users` sheet. Each row is saved on its own (one bad row never blocks the others).
     *
     * @return array{summary: array{success: int, created: int, updated: int, skipped: int, failed: int}, issues: list<string>, rows: list<array{row: int, status: string, emp_code: string, messages: list<string>}>}
     */
    public function import(string $path, ?int $actorId = null): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([UsersWorkbookColumns::SHEET]);
        $book = $reader->load($path);
        $cells = $book->getSheetByName(UsersWorkbookColumns::SHEET)?->toArray(null, false, false) ?? [];
        $book->disconnectWorksheets();

        $map = UsersWorkbookColumns::map(array_shift($cells) ?? []);
        $rows = [];
        foreach ($cells as $i => $line) {
            $row = [];
            foreach ($map as $index => $key) {
                $value = $line[$index] ?? null;
                $row[$key] = is_float($value) && floor($value) === $value ? sprintf('%.0f', $value) : $value;
            }
            if (implode('', array_map(fn ($v) => trim((string) $v), $row)) !== '') {
                $rows[$i + 2] = $row;   // keyed by sheet row; blank lines (the template's spare rows) skipped
            }
        }

        return $this->saveRows($rows, $actorId, 'Row');
    }

    /**
     * Save rows one by one through `UserRowService` (one bad row never blocks the others); used by the file import and
     * the bulk screen. Keys of $rows are the row labels used in the issues list (sheet row numbers / grid indexes).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{summary: array{success: int, created: int, updated: int, skipped: int, failed: int}, issues: list<string>, rows: list<array{row: int, status: string, emp_code: string, messages: list<string>}>}
     */
    public function saveRows(array $rows, ?int $actorId = null, string $label = 'Row'): array
    {
        $summary = ['success' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $issues = [];
        $results = [];

        foreach ($rows as $rowNo => $row) {
            $result = $this->rows->save($row, $actorId);
            $summary[$result['status']]++;
            if ($result['status'] !== 'failed') {
                $summary['success']++;
            }
            foreach ($result['messages'] as $message) {
                $issues[] = "{$label} {$rowNo} · {$result['emp_code']} · ".($result['status'] === 'failed' ? 'FAILED: ' : '').$message;
            }
            $results[] = ['row' => (int) $rowNo] + $result;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ['summary' => $summary, 'issues' => $issues, 'rows' => $results];
    }

    /**
     * Masters for the bulk screen's pickers: CODE => name per type, and the parent → children maps.
     *
     * @return array{names: array<string, array<string, string>>, children: array<string, array<string, list<string>>>}
     */
    public function masterPayload(): array
    {
        $masters = $this->rows->masters();
        $names = [];
        foreach (['branch', 'location', 'department', 'division', 'vertical', 'segment', 'sub_segment', 'model', 'designation', 'employee'] as $type) {
            $names[$type] = $masters->names($type);
        }

        return ['names' => $names, 'children' => [
            'location<branch' => $masters->childMap('location', 'branch'),
            'division<department' => $masters->childMap('division', 'department'),
            'sub_segment<segment' => $masters->childMap('sub_segment', 'segment'),
            'model<sub_segment' => $masters->childMap('model', 'sub_segment'),
            'model<segment' => $masters->childMap('model', 'segment'),
        ]];
    }

    /**
     * One row per user linked to an employee, in the workbook's keys.
     *
     * @return list<array<string, string|null>>
     */
    public function userRows(): array
    {
        $users = User::query()->whereNotNull('employee_code')->orderBy('employee_code')->get(['id', 'employee_code']);
        $employees = Employee::withTrashed()->with('person:person_code,display_name,aadhaar_no')
            ->whereIn('code', $users->pluck('employee_code'))->get()
            ->keyBy(fn (Employee $e) => strtoupper((string) $e->getAttribute('code')));
        $contacts = PersonContact::query()->whereIn('person_code', $employees->pluck('person_code')->filter())
            ->get(['person_code', 'data_type', 'contact_type', 'contact_detail'])->groupBy('person_code');
        $allScopes = UserScope::query()->whereIn('user_id', $users->pluck('id'))->where('is_active', true)
            ->get(['user_id', 'scope_type', 'scope_code'])->groupBy('user_id');

        $rows = [];
        foreach ($users as $user) {
            $employee = $employees->get(strtoupper((string) $user->getAttribute('employee_code')));
            if ($employee === null) {
                continue;
            }
            /** @var Collection<int, PersonContact> $own */
            $own = $contacts->get((string) $employee->getAttribute('person_code'), collect());
            $contact = fn (string $dataType, string $contactType) => $own
                ->first(fn (PersonContact $c) => $c->getAttribute('data_type') === $dataType && $c->getAttribute('contact_type') === $contactType)
                ?->getAttribute('contact_detail');
            $scopes = $allScopes->get($user->id, collect())->groupBy(fn (UserScope $s) => strtolower((string) $s->getAttribute('scope_type')))
                ->map(fn ($items) => $items->map(fn (UserScope $s) => strtoupper((string) $s->getAttribute('scope_code')))->unique()->values()->all())->all();
            $get = fn (string $column) => strtoupper((string) $employee->getAttribute($column));

            $branch = $get('primary_branch_code');
            $dept = $get('primary_dept_code');
            $aadhaar = (string) $employee->person?->getAttribute('aadhaar_no');

            $rows[] = [
                'emp_code' => $employee->getAttribute('code'),
                'name' => $employee->person?->getAttribute('display_name'),
                'personal_email' => $contact('Email', 'Alternate'),
                'official_email' => $contact('Email', 'Primary'),
                'personal_mobile' => $contact('Mobile', 'Primary'),
                'official_mobile' => $contact('Mobile', 'Office'),
                'mile_id' => $employee->getAttribute('mile_id'),
                'aadhaar' => strlen($aadhaar) === 12 ? 'XXXXXXXX'.substr($aadhaar, -4) : null,
                'primary_branch' => $branch ?: null,
                'addon_branch' => $this->cell($scopes['branch'] ?? [], $branch),
                'primary_location' => $get('primary_loc_code') ?: null,
                'addon_location' => $this->cell($scopes['location'] ?? [], $get('primary_loc_code')),
                'primary_department' => $dept ?: null,
                'addon_department' => $this->cell($scopes['department'] ?? [], $dept),
                'primary_division' => $get('primary_div_code') ?: null,
                'addon_division' => $this->cell($scopes['division'] ?? [], $get('primary_div_code')),
                'designation' => $get('designation_code') ?: null,
                'vertical' => $this->cell($scopes['vertical'] ?? []),
                'segment' => $this->cell($scopes['segment'] ?? []),
                'sub_segment' => $this->cell($scopes['sub_segment'] ?? []),
                'models' => $this->cell($scopes['model'] ?? []),
                'reporting_manager' => $get('reporting_manager_code') ?: null,
            ];
        }

        return $rows;
    }

    /**
     * Scope rows → cell text, the inverse of `UserRowService`: no rows = `ALL` (unrestricted); for an org type with a
     * primary, only the primary = `NONE`, else the add-on codes (rows without the primary).
     *
     * @param  list<string>  $rows
     */
    private function cell(array $rows, ?string $primary = null): string
    {
        if ($rows === []) {
            return UsersWorkbookColumns::ALL;
        }
        $codes = array_values(array_diff($rows, [strtoupper((string) $primary)]));
        if ($primary !== null && $codes === []) {
            return UsersWorkbookColumns::NONE;
        }
        sort($codes);

        return implode(', ', $codes);
    }

    /**
     * The `Lists` sheet (code + name per list, named ranges) and the Users sheet's validations.
     */
    private function addLists(Spreadsheet $book, Worksheet $sheet, int $lastRow): void
    {
        $masters = $this->rows->masters();
        $lists = $book->createSheet()->setTitle(UsersWorkbookColumns::LISTS_SHEET);
        $listCol = 1;
        $write = function (string $title, array $names, ?string $rangeName, bool $all = false, bool $none = false) use (&$listCol, $lists, $book): void {
            $code = Coordinate::stringFromColumnIndex($listCol);
            $name = Coordinate::stringFromColumnIndex($listCol + 1);
            $lists->setCellValue($code.'1', $title);
            $lists->setCellValue($name.'1', 'Name');
            $lists->getStyle("{$code}1:{$name}1")->getFont()->setBold(true);
            $entries = ($all ? [UsersWorkbookColumns::ALL => 'every code'] : []) + $names + ($none ? [UsersWorkbookColumns::NONE => 'none (clears)'] : []);
            $r = 2;
            foreach ($entries as $value => $label) {
                $lists->setCellValueExplicit($code.$r, (string) $value, DataType::TYPE_STRING);
                $lists->setCellValueExplicit($name.$r, (string) $label, DataType::TYPE_STRING);
                $r++;
            }
            if ($rangeName !== null) {
                $book->addNamedRange(new NamedRange($rangeName, $lists, '$'.$code.'$2:$'.$code.'$'.max(2, $r - 1)));
            }
            $lists->getColumnDimension($code)->setWidth(16);
            $lists->getColumnDimension($name)->setWidth(28);
            $listCol += 3;
        };

        $col = fn (string $key) => Coordinate::stringFromColumnIndex((int) array_search($key, array_keys(UsersWorkbookColumns::HEADERS), true) + 1);
        $range = fn (string $key) => $col($key).'2:'.$col($key).$lastRow;
        $label = fn (string $key) => rtrim(UsersWorkbookColumns::HEADERS[$key], '*');

        // Single-value dropdowns
        foreach (['primary_branch' => 'branch', 'primary_department' => 'department', 'designation' => 'designation', 'reporting_manager' => 'employee'] as $key => $type) {
            $write($label($key), $masters->names($type), 'LST_'.strtoupper($type));
            $this->listValidation($sheet, $range($key), '=LST_'.strtoupper($type), $label($key));
        }

        // Multi-value lists (read by people; the cells take comma-separated codes)
        $multi = ['addon_branch' => 'branch', 'addon_location' => 'location', 'addon_department' => 'department', 'addon_division' => 'division',
            'vertical' => 'vertical', 'segment' => 'segment', 'sub_segment' => 'sub_segment', 'models' => 'model'];
        foreach ($multi as $key => $type) {
            $write($label($key), $masters->names($type), null, true, $key !== 'vertical');
            $this->promptOnly($sheet, $range($key), $label($key), $key === 'vertical'
                ? 'Comma-separated vertical codes, or ALL (Lists sheet). Required; NONE not allowed.'
                : 'Comma-separated codes, ALL or NONE (Lists sheet). Blank keeps what is stored; NONE clears.');
        }

        // Dependent primaries: one named range per parent, picked by the row's parent cell
        foreach (['primary_location' => ['location', 'branch', 'LOC_', 'primary_branch'], 'primary_division' => ['division', 'department', 'DIV_', 'primary_department']] as $key => [$type, $parentType, $prefix, $parentKey]) {
            $names = $masters->names($type);
            foreach ($masters->childMap($type, $parentType) as $parent => $children) {
                $write($label($key).' · '.$parent, array_intersect_key($names, array_flip($children)), $prefix.$this->rangeSuffix($parent));
            }
            $this->listValidation($sheet, $range($key),
                '=INDIRECT("'.$prefix.'"&SUBSTITUTE(SUBSTITUTE(SUBSTITUTE($'.$col($parentKey).'2,"-","_")," ","_"),".","_"))', $label($key));
        }

        $lists->freezePane('A2');
    }

    /** A valid Excel name part for a code (the INDIRECT formula makes the same substitutions). */
    private function rangeSuffix(string $code): string
    {
        return str_replace(['-', ' ', '.'], '_', strtoupper($code));
    }

    private function listValidation(Worksheet $sheet, string $range, string $formula, string $label): void
    {
        $v = new DataValidation;
        $v->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)->setAllowBlank(true)
            ->setShowDropDown(true)->setShowErrorMessage(true)->setShowInputMessage(true)
            ->setErrorTitle($label.': pick from the list')
            ->setError('Choose a '.$label.' code from the dropdown (the masters). New values are added in the masters first.')
            ->setPromptTitle($label)->setPrompt('Pick a code from the list')
            ->setFormula1($formula);
        $sheet->setDataValidation($range, $v);
    }

    private function promptOnly(Worksheet $sheet, string $range, string $label, string $prompt): void
    {
        $v = new DataValidation;
        $v->setType(DataValidation::TYPE_NONE)->setAllowBlank(true)->setShowInputMessage(true)
            ->setPromptTitle($label)->setPrompt($prompt);
        $sheet->setDataValidation($range, $v);
    }

    private function addInstructions(Spreadsheet $book): void
    {
        $sheet = $book->createSheet()->setTitle('Instructions');
        $lines = [
            'Users workbook (DEC-089)',
            '',
            '• One row per employee user, matched by Emp Code. New codes create the person, employee and login (initial password = personal contact number).',
            '• Columns with * are required for a new employee. Values are master codes (see the Lists sheet); pick single values from the dropdowns.',
            '• Primary Location follows the row\'s Primary Branch, Primary Division its Primary Department. A blank one takes the same-code child of the parent.',
            '• Addon / Vertical / Segment / Sub Segment / Models: comma-separated codes, ALL or NONE. Blank keeps what is stored; NONE clears; ALL grants every allowed code.',
            '• AddOn Location: the primary branch\'s other locations or any location of the add-on branches (Add On Divisions likewise, from departments).',
            '• Segment / Sub Segment / Models: ALL (or no codes) = every vehicle.',
            '• Vertical is required; several codes are allowed. Primaries never take ALL / NONE.',
            '• Aadhaar is shown masked: leave it as it is to keep the stored number, or type the full 12 digits to replace it.',
            '• Reporting Manager: an employee code; NONE clears it. OEM Mile ID: NONE clears it.',
            '• Every change of designation, primaries, vertical, manager or scopes is kept in the employee history.',
        ];
        foreach ($lines as $i => $line) {
            $sheet->setCellValue('A'.($i + 1), $line);
        }
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getColumnDimension('A')->setWidth(150);
    }
}

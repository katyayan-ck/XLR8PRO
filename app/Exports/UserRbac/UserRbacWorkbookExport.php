<?php

declare(strict_types=1);

namespace App\Exports\UserRbac;

use App\Imports\Sheets\UserScopesSheetImport;
use App\Imports\UsersImportWorkbook;
use App\Services\IAM\UserRbacExportService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * User & RBAC workbook (DEC-040):
 *   Instructions · Permissions · Roles · Users_Import · User_Scopes · Lists (hidden)
 *
 * `Users_Import` and `User_Scopes` are importable as-is (Users → Bulk import or
 * `php artisan import:users`). Master-data cells are dropdowns fed by named ranges on the
 * Lists sheet; invalid entries are rejected by Excel.
 *
 *   Excel::store(new UserRbacWorkbookExport(app(UserRbacExportService::class)), 'exports/users.xlsx');
 */
final class UserRbacWorkbookExport implements WithMultipleSheets
{
    /** Empty rows below the data that still carry dropdowns, for new users / scope rows. */
    private const SPARE_USER_ROWS = 300;

    private const SPARE_SCOPE_ROWS = 2000;

    private const EDITABLE_FILL = '1F4E78';

    private const REQUIRED_FILL = 'C00000';

    private const READ_ONLY_FILL = '808080';

    /** @param bool $includeUsers false = empty template (lists, reference sheets, no user rows) */
    public function __construct(
        private readonly UserRbacExportService $data,
        private readonly bool $includeUsers = true,
    ) {}

    public function sheets(): array
    {
        $users = $this->includeUsers ? $this->data->userRows() : [];
        $scopes = $this->includeUsers ? $this->data->scopeRows() : [];
        $lists = $this->data->lists();
        $userLastRow = count($users) + 1 + self::SPARE_USER_ROWS;
        $scopeLastRow = count($scopes) + 1 + self::SPARE_SCOPE_ROWS;

        return [
            new UserRbacSheet('Instructions', ['Users & RBAC workbook'], $this->instructions(),
                function (AfterSheet $e) {
                    $s = $e->sheet->getDelegate();
                    $s->getColumnDimension('A')->setWidth(130);
                    $s->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                    $s->getStyle('A:A')->getAlignment()->setWrapText(true);
                }),
            new UserRbacSheet('Permissions',
                ['Module Code', 'Module', 'Process Code', 'Process', 'Permission', 'Guard', 'Roles With It'],
                $this->data->permissionRows(),
                fn (AfterSheet $e) => $this->styleTable($e->sheet->getDelegate(), [14, 24, 14, 28, 34, 8, 14])),
            new UserRbacSheet('Roles',
                ['Designation Code', 'Designation (Role)', 'Category', 'Hierarchy Level', 'Parent Designation', 'Active', 'Users', 'Permission Count', 'Permissions'],
                $this->data->roleRows(),
                fn (AfterSheet $e) => $this->styleTable($e->sheet->getDelegate(), [18, 34, 14, 14, 18, 8, 8, 16, 120])),
            new UserRbacSheet(UsersImportWorkbook::SHEET, $this->data->userHeaders(), $users,
                fn (AfterSheet $e) => $this->styleUsers($e->sheet->getDelegate(), $userLastRow), asText: true),
            new UserRbacSheet(UserScopesSheetImport::SHEET,
                ['Emp Code*', 'Scope Type*', 'Scope Value*', UserRbacExportService::READ_ONLY_PREFIX.'Employee Name'],
                $scopes,
                fn (AfterSheet $e) => $this->styleScopes($e->sheet->getDelegate(), $scopeLastRow), asText: true),
            new UserRbacSheet('Lists', array_keys($lists), $this->columns($lists),
                fn (AfterSheet $e) => $this->defineLists($e->sheet->getDelegate(), $lists, $userLastRow), asText: true),
        ];
    }

    /** @param list<int> $widths */
    private function styleTable(Worksheet $sheet, array $widths): void
    {
        foreach ($widths as $i => $width) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($width);
        }
        $last = Coordinate::stringFromColumnIndex(count($widths));
        $this->header($sheet, "A1:{$last}1", self::EDITABLE_FILL);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$last}".max(2, $sheet->getHighestRow()));
    }

    private function styleUsers(Worksheet $sheet, int $lastRow): void
    {
        $headers = $this->data->userHeaders();
        $editable = count(UserRbacExportService::USER_COLUMNS);
        $lastEditable = Coordinate::stringFromColumnIndex($editable);
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));

        foreach ($headers as $i => $header) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $readOnly = $i >= $editable;
            $fill = $readOnly ? self::READ_ONLY_FILL : (str_ends_with($header, '*') ? self::REQUIRED_FILL : self::EDITABLE_FILL);
            $this->header($sheet, "{$col}1", $fill);
            $sheet->getColumnDimension($col)->setWidth($readOnly ? ($header === UserRbacExportService::READ_ONLY_PREFIX.'Permissions' ? 80 : 26) : 22);
        }

        // Editable cells stay text when typed into (phone, Aadhaar, account numbers, dates).
        $sheet->getStyle("A2:{$lastEditable}{$lastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $firstReadOnly = Coordinate::stringFromColumnIndex($editable + 1);
        $sheet->getStyle("{$firstReadOnly}2:{$lastCol}{$lastRow}")->getFont()->getColor()->setRGB('7F7F7F');

        $i = 0;
        foreach (UserRbacExportService::USER_COLUMNS as $header => $list) {
            $i++;
            if ($list !== null) {
                $col = Coordinate::stringFromColumnIndex($i);
                $sheet->setDataValidation("{$col}2:{$col}{$lastRow}", $this->listValidation("=L_{$list}", $header));
            }
        }

        $sheet->freezePane('C2');
        $sheet->setAutoFilter("A1:{$lastCol}".max(2, $sheet->getHighestRow()));
    }

    private function styleScopes(Worksheet $sheet, int $lastRow): void
    {
        foreach ([['A', 16, self::REQUIRED_FILL], ['B', 14, self::REQUIRED_FILL], ['C', 50, self::REQUIRED_FILL], ['D', 34, self::READ_ONLY_FILL]] as [$col, $width, $fill]) {
            $sheet->getColumnDimension($col)->setWidth($width);
            $this->header($sheet, "{$col}1", $fill);
        }

        $sheet->setDataValidation("A2:A{$lastRow}", $this->listValidation('=L_emp_code', 'Emp Code'));
        $sheet->setDataValidation("B2:B{$lastRow}", $this->listValidation('=L_scope_type', 'Scope Type'));
        // Value list follows the row's scope type: branch → L_branch, sub_segment → L_sub_segment…
        $sheet->setDataValidation("C2:C{$lastRow}", $this->listValidation('=INDIRECT("L_"&$B2)', 'Scope Value'));

        $users = UsersImportWorkbook::SHEET;
        for ($row = 2; $row <= $lastRow; $row++) {
            $sheet->getCell("D{$row}")->setValueExplicit(
                "=IF(\$A{$row}=\"\",\"\",IFERROR(INDEX({$users}!\$B:\$B,MATCH(\$A{$row},{$users}!\$A:\$A,0)),\"(not in {$users})\"))",
                DataType::TYPE_FORMULA
            );
        }
        $sheet->getStyle("D2:D{$lastRow}")->getFont()->getColor()->setRGB('7F7F7F');

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:D'.max(2, $sheet->getHighestDataRow('A')));
    }

    /** @param array<string, list<string>> $lists */
    private function defineLists(Worksheet $sheet, array $lists, int $userLastRow): void
    {
        $book = $sheet->getParent();
        $i = 0;
        foreach ($lists as $key => $values) {
            $i++;
            $col = Coordinate::stringFromColumnIndex($i);
            $last = max(2, count($values) + 1);
            $book->addNamedRange(new NamedRange("L_{$key}", $sheet, "\${$col}\$2:\${$col}\${$last}"));
            $sheet->getColumnDimension($col)->setWidth(30);
        }

        $users = $book->getSheetByName(UsersImportWorkbook::SHEET);
        $book->addNamedRange(new NamedRange('L_emp_code', $users, "\$A\$2:\$A\${$userLastRow}"));

        $this->header($sheet, 'A1:'.Coordinate::stringFromColumnIndex(count($lists)).'1', self::READ_ONLY_FILL);
        $sheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
        $book->setActiveSheetIndex(0);
    }

    private function listValidation(string $formula, string $field): DataValidation
    {
        return (new DataValidation)
            ->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Invalid '.rtrim($field, '*'))
            ->setError('Pick a value from the dropdown list.')
            ->setFormula1($formula);
    }

    private function header(Worksheet $sheet, string $range, string $fill): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
        $style->getAlignment()->setWrapText(true);
    }

    /**
     * Lists as columns: row n holds the n-th value of every list.
     *
     * @param  array<string, list<string>>  $lists
     * @return list<list<string|null>>
     */
    private function columns(array $lists): array
    {
        $rows = [];
        $height = max(array_map('count', $lists));
        for ($r = 0; $r < $height; $r++) {
            $rows[] = array_map(fn (array $values) => $values[$r] ?? null, array_values($lists));
        }

        return $rows;
    }

    /** @return list<list<string>> */
    private function instructions(): array
    {
        $users = UsersImportWorkbook::SHEET;
        $scopes = UserScopesSheetImport::SHEET;

        return array_map(fn (string $line) => [$line], [
            'Generated '.now()->format('d-m-Y H:i').'. Sheets:',
            '• Permissions: every permission by Module → Process (read-only).',
            '• Roles: every designation (designations are the roles) with its permissions (read-only).',
            "• {$users}: one row per user. Blue/red headers are editable (red = required); grey [Read-only] columns are ignored on import.",
            "• {$scopes}: the user's data scopes, one row per value. Multi-select = one row per branch / location / model…",
            '',
            'Editing rules:',
            '• Use the dropdowns; Excel rejects values that are not in the list. Values look like "Name (CODE)"; only the CODE is used.',
            '• ALL = every active value of that type (resolved when you import).',
            '• A blank cell clears that field. Do not rename sheets or headers, and do not paste over the dropdown cells.',
            "• New user: add a row to {$users} (Emp Code, Employee Name and Personal Contact Number are required; the initial password is the mobile number),",
            "  then add their scope rows in {$scopes}.",
            "• Scopes: for every user listed in {$scopes}, the listed rows REPLACE that user's scopes (removed ones are deactivated, not deleted).",
            "  The user's primary branch / location / department / division / vertical / segment / sub segment always stays in scope.",
            "  Users not listed in {$scopes} keep their scopes. A user with any invalid scope row is skipped entirely and reported.",
            '• Role = Designation. Permissions are changed on the designation (Org → Designation), not in this file.',
            '',
            'Import: Admin → Org → Users → Bulk import (upload this file), or `php artisan import:users <file>`.',
            'Re-export after importing to see the result: Admin → Org → Users → Bulk import → Export users & RBAC, or `php artisan users:export-rbac`.',
        ]);
    }
}

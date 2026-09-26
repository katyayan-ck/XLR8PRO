<?php

declare(strict_types=1);

namespace App\Imports;

use App\Imports\Sheets\StandaloneUsersImport;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Master-workbook entry point for the user bulk import: only the `Users_Import` sheet is
 * processed. Feeding every sheet to the row importer let the `Reporting` sheet (which also
 * has an "Emp Code" column) create nameless persons and re-point employees (BUG-162).
 */
final class UsersImportWorkbook implements SkipsUnknownSheets, WithMultipleSheets
{
    public const SHEET = 'Users_Import';

    public function __construct(private readonly StandaloneUsersImport $sheet = new StandaloneUsersImport) {}

    public function sheets(): array
    {
        return [self::SHEET => $this->sheet];
    }

    public function onUnknownSheet($sheetName): void
    {
        // The workbook has no Users_Import sheet; the command reports this before importing.
    }

    public function rows(): StandaloneUsersImport
    {
        return $this->sheet;
    }
}

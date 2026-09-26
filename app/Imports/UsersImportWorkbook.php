<?php

declare(strict_types=1);

namespace App\Imports;

use App\Imports\Sheets\StandaloneUsersImport;
use App\Imports\Sheets\UserScopesSheetImport;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Master-workbook entry point for the user bulk import. Only `Users_Import` and, when present,
 * `User_Scopes` are processed, in that order (sheets run in the order of sheets()), so users
 * exist before their scopes are replaced. Feeding every sheet to the row importer let the
 * `Reporting` sheet (which also has an "Emp Code" column) create nameless persons and re-point
 * employees (BUG-162). The read-only sheets of the user & RBAC export are ignored (DEC-040).
 */
final class UsersImportWorkbook implements SkipsUnknownSheets, WithMultipleSheets
{
    public const SHEET = 'Users_Import';

    public function __construct(
        private readonly StandaloneUsersImport $sheet = new StandaloneUsersImport,
        private readonly UserScopesSheetImport $scopes = new UserScopesSheetImport,
    ) {}

    public function sheets(): array
    {
        return [
            self::SHEET => $this->sheet,
            UserScopesSheetImport::SHEET => $this->scopes,
        ];
    }

    public function onUnknownSheet($sheetName): void
    {
        // A sheet listed in sheets() is missing (User_Scopes is optional; the callers check Users_Import).
    }

    public function rows(): StandaloneUsersImport
    {
        return $this->sheet;
    }

    public function scopes(): UserScopesSheetImport
    {
        return $this->scopes;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Imports\Sheets\StandaloneUsersImport;
use App\Imports\Sheets\UserScopesSheetImport;
use App\Imports\UsersImportWorkbook;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Bulk create/update of Person + Employee + User (+ contacts, addresses, banking, scopes,
 * role) from the users workbook, plus the User_Scopes sheet of `users:export-rbac` (DEC-040). Idempotent: existing employees are matched by Emp Code and
 * keep their person_code (DEC-035).
 *
 *   php artisan import:users docs/reference/pricing/data/userdata.xlsx
 */
class ImportUsersCommand extends Command
{
    protected $signature = 'import:users {file : Path to the Excel workbook (Users_Import sheet) or a single-sheet file}';

    protected $description = 'Bulk create/update users (person, employee, user, scopes, role) from Excel';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $sheets = IOFactory::createReaderForFile($file)->listWorksheetNames($file);
        $rows = new StandaloneUsersImport;
        $scopes = new UserScopesSheetImport;

        if (in_array(UsersImportWorkbook::SHEET, $sheets, true)) {
            $this->info('Importing sheet '.UsersImportWorkbook::SHEET
                .(in_array(UserScopesSheetImport::SHEET, $sheets, true) ? ' + '.UserScopesSheetImport::SHEET : '')." from {$file}");
            Excel::import(new UsersImportWorkbook($rows, $scopes), $file);
        } elseif (count($sheets) === 1) {
            $this->info("Importing single sheet '{$sheets[0]}' from {$file}");
            Excel::import($rows, $file);
        } else {
            $this->error('Workbook has no '.UsersImportWorkbook::SHEET.' sheet (found: '.implode(', ', $sheets).').');

            return self::FAILURE;
        }

        $s = $rows->summary();
        $this->table(['Created', 'Updated', 'Skipped', 'Failed'], [[$s['created'], $s['updated'], $s['skipped'], $s['failed']]]);

        if (in_array(UserScopesSheetImport::SHEET, $sheets, true)) {
            $sc = $scopes->summary();
            $this->table(['Scope users', 'Added', 'Re-activated', 'Removed', 'Users skipped', 'Invalid rows'],
                [[$sc['users'], $sc['inserted'], $sc['activated'], $sc['deactivated'], $sc['skipped_users'], $sc['failed_rows']]]);
        }

        return $s['failed'] > 0 || $scopes->summary()['failed_rows'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

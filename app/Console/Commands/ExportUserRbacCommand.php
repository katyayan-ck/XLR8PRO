<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exports\UserRbac\UserRbacWorkbookExport;
use App\Services\IAM\UserRbacExportService;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Users & RBAC workbook (DEC-040): permissions by module/process, roles with permissions,
 * users with scopes — importable back with `php artisan import:users <file>`.
 *
 *   php artisan users:export-rbac
 *   php artisan users:export-rbac --path=D:/exports/users.xlsx
 */
class ExportUserRbacCommand extends Command
{
    protected $signature = 'users:export-rbac {--path= : Output .xlsx path (default storage/app/exports/users-rbac-<timestamp>.xlsx)}';

    protected $description = 'Export users, designations (roles), permissions and scopes to an importable Excel workbook';

    public function handle(UserRbacExportService $data): int
    {
        $path = (string) ($this->option('path') ?: storage_path('app/exports/users-rbac-'.now()->format('Ymd-His').'.xlsx'));

        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
            $this->error('Cannot create directory '.dirname($path));

            return self::FAILURE;
        }

        file_put_contents($path, Excel::raw(new UserRbacWorkbookExport($data), ExcelFormat::XLSX));

        $this->info("Written {$path}");

        return self::SUCCESS;
    }
}

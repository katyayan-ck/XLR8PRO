<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\Sheets\StandaloneUsersImport;

class ImportUsersCommand extends Command
{
    protected $signature = 'import:users {file : Path to Excel file}';
    protected $description = 'Standalone import for Users_Import sheet only';

    public function handle()
    {
        $file = $this->argument('file');
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $this->info("🚀 Starting standalone Users import from: {$file}");

        $import = new StandaloneUsersImport();
        Excel::import($import, $file);

        $this->info("✅ Standalone Users Import Completed!");
        return 0;
    }
}
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\IAM\UserResetService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Owner #18 (DEC-095, W18j): keeps only the listed login accounts and permanently removes every other user, its access
 * rows and the HR identity (employees, persons) behind it — before the new user data is imported. **Local only.**
 * Without --apply it only reports. With --apply it first dumps every affected table to
 * `storage/app/backups/users-reset-<timestamp>.sql` (restore with `mysql <db> < file`), then removes in one transaction.
 *
 *   php artisan users:reset --keep=superadmin --keep=dev1                report
 *   php artisan users:reset --keep-file=storage/app/keep-users.txt --apply  (one username per line)
 */
class ResetUsers extends Command
{
    protected $signature = 'users:reset {--keep=* : username to keep (repeat)} {--keep-file= : file with one username per line}
        {--apply : remove (default: report only)} {--bin-dir= : directory with mysqldump (default: MYSQL_BIN_DIR env, else PATH)}';

    protected $description = 'Keep only the listed user accounts and permanently remove all other users / employees / persons (local only)';

    public function handle(UserResetService $service): int
    {
        if (! app()->environment('local')) {
            $this->error('users:reset runs only in the local environment.');

            return self::FAILURE;
        }

        $keep = (array) $this->option('keep');
        if ($file = $this->option('keep-file')) {
            if (! is_file((string) $file)) {
                $this->error("Keep file not found: {$file}");

                return self::FAILURE;
            }
            $keep = array_merge($keep, preg_split('/\R/', (string) file_get_contents((string) $file)) ?: []);
        }

        $apply = (bool) $this->option('apply');
        $plan = $service->plan($keep);
        if (! $plan['ok']) {
            $this->error((string) $plan['error']);

            return self::FAILURE;
        }

        $this->info(($apply ? 'Removing' : 'Report only — add --apply to remove').'. Keeping: '.implode(', ', $plan['keep']));
        $this->table(['What', 'Rows'], collect($plan['counts'])->map(fn (int $n, string $what) => [$what, $n])->values()->all());
        $this->line("Media files of removed users / persons left in place: {$plan['media_left']}.");

        if (! $apply) {
            return self::SUCCESS;
        }

        $backup = $this->backup($service->backupTables());
        if ($backup === null) {
            return self::FAILURE;
        }
        $this->info("Backup: {$backup}");
        $service->apply($keep);
        $this->info('Done.');

        return self::SUCCESS;
    }

    /** @param  list<string>  $tables */
    private function backup(array $tables): ?string
    {
        $connection = config('database.connections.mysql');
        $binDir = (string) ($this->option('bin-dir') ?: config('database.mysql_bin_dir', ''));
        $dump = $binDir !== '' ? '"'.rtrim($binDir, '\\/').DIRECTORY_SEPARATOR.'mysqldump"' : 'mysqldump';
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $file = $dir.DIRECTORY_SEPARATOR.'users-reset-'.now()->format('Ymd-His').'.sql';

        $command = sprintf('%s --host=%s --port=%s --user=%s --single-transaction --no-tablespaces %s %s --result-file=%s',
            $dump, escapeshellarg((string) $connection['host']), escapeshellarg((string) $connection['port']),
            escapeshellarg((string) $connection['username']), escapeshellarg((string) $connection['database']),
            implode(' ', array_map('escapeshellarg', $tables)), escapeshellarg($file));

        // Password via environment so it never appears in the process list.
        $result = Process::timeout(600)->env(['MYSQL_PWD' => (string) $connection['password']])->run($command);
        if ($result->failed() || ! is_file($file) || filesize($file) === 0) {
            $this->error('Backup failed — nothing removed. '.trim($result->errorOutput()));

            return null;
        }

        return $file;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Copies the local development database (schema + data) into a separate
 * `*_testing` database so the test suite never touches the real one.
 *
 * Tests still rely on real reference data (RTO rules, org users, …), which is
 * why this is a full copy rather than an empty schema. See DEC-011.
 */
class RefreshTestingDatabase extends Command
{
    protected $signature = 'testing:refresh-db
        {--source= : Source database (default: the configured mysql database)}
        {--target= : Target database (default: <source>_testing)}
        {--bin-dir= : Directory containing mysqldump/mysql (default: MYSQL_BIN_DIR env, else PATH)}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Copy the local dev database into the *_testing database used by phpunit';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }

        $connection = config('database.connections.mysql');
        $source = (string) ($this->option('source') ?: $connection['database']);
        $target = (string) ($this->option('target') ?: $source.'_testing');

        if ($target === $source || ! str_ends_with($target, '_testing')) {
            $this->error("Target must differ from the source and end with '_testing' (got '{$target}').");

            return self::FAILURE;
        }

        if (! preg_match('/^[A-Za-z0-9_]+$/', $source.$target)) {
            $this->error('Database names may only contain letters, digits and underscores.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Replace database '{$target}' with a copy of '{$source}'?", true)) {
            return self::SUCCESS;
        }

        $binDir = (string) ($this->option('bin-dir') ?: env('MYSQL_BIN_DIR', ''));
        $bin = fn (string $name): string => $binDir !== ''
            ? '"'.rtrim($binDir, '\\/').DIRECTORY_SEPARATOR.$name.'"'
            : $name;

        $host = (string) $connection['host'];
        $port = (string) $connection['port'];
        $user = (string) $connection['username'];

        DB::statement("DROP DATABASE IF EXISTS `{$target}`");
        DB::statement("CREATE DATABASE `{$target}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $auth = sprintf('--host=%s --port=%s --user=%s', escapeshellarg($host), escapeshellarg($port), escapeshellarg($user));
        $command = sprintf(
            // Small multi-row INSERTs keep the local mysqld within its memory limits
            // (a default dump ran it out of memory on the large pincode/enquiry tables).
            '%s %s --single-transaction --routines --triggers --no-tablespaces --net-buffer-length=65536 %s | %s %s --max-allowed-packet=64M %s',
            $bin('mysqldump'),
            $auth,
            $source,
            $bin('mysql'),
            $auth,
            $target,
        );

        $this->info("Copying {$source} → {$target} …");

        // Password via environment so it never appears in the process list.
        $result = Process::timeout(1800)
            ->env(['MYSQL_PWD' => (string) $connection['password']])
            ->run($command);

        if ($result->failed()) {
            $this->error(trim($result->errorOutput()) ?: 'mysqldump/mysql failed.');

            return self::FAILURE;
        }

        $tables = DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = ?', [$target])->n;
        $this->info("Done: {$tables} tables in {$target}.");

        return self::SUCCESS;
    }
}

<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * DEC-093: no code queries the database through the `DB` facade — every read and write goes through an Eloquent model
 * (and, for entity writes, its entity service, DEC-050). Allowed: transaction control (`DB::transaction`,
 * `beginTransaction`, `commit`, `rollBack`); migrations are out of scope (they must not depend on models that change).
 *
 * The legacy uses are listed per file in `db-facade-baseline.json` (to-do W15 converts them). This test is a ratchet:
 * a file may never gain a use, a new file may never add one, and a converted file must lower its count in the baseline
 * (so the list only shrinks). After converting, refresh the list with:
 *
 *   UPDATE_DB_FACADE_BASELINE=1 php artisan test --compact tests/Unit/Architecture/NoDbFacadeQueriesTest.php
 *
 * (the refresh refuses to raise any count).
 */
class NoDbFacadeQueriesTest extends TestCase
{
    /** Scanned roots, relative to the project root. */
    private const ROOTS = ['app', 'routes', 'config', 'database/seeders', 'database/factories', 'tests'];

    /** `DB::` calls that run or build SQL; transaction control is allowed. */
    private const PATTERN = '/(?<![\w$>])\\\\?(?:Illuminate\\\\Support\\\\Facades\\\\)?DB::(?!transaction\b|beginTransaction\b|commit\b|rollBack\b)[A-Za-z_]+/';

    public function test_no_file_adds_a_db_facade_query_beyond_the_legacy_baseline(): void
    {
        $root = dirname(__DIR__, 3);
        $baselineFile = __DIR__.'/db-facade-baseline.json';
        /** @var array<string, int> $baseline */
        $baseline = json_decode((string) file_get_contents($baselineFile), true) ?: [];
        $found = $this->scan($root);

        if (getenv('UPDATE_DB_FACADE_BASELINE') === '1') {
            $raised = array_filter($found, fn (int $n, string $f) => $n > ($baseline[$f] ?? 0), ARRAY_FILTER_USE_BOTH);
            $this->assertSame([], $raised, 'The baseline never grows: convert these uses to Eloquent instead.');
            ksort($found);
            file_put_contents($baselineFile, json_encode($found, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
            $this->addToAssertionCount(1);

            return;
        }

        $problems = [];
        foreach ($found as $file => $count) {
            $allowed = $baseline[$file] ?? 0;
            if ($count > $allowed) {
                $problems[] = "{$file}: {$count} DB:: queries (allowed {$allowed}) — use the Eloquent model (DEC-093)";
            }
        }
        foreach ($baseline as $file => $allowed) {
            if (($found[$file] ?? 0) < $allowed) {
                $problems[] = "{$file}: now ".($found[$file] ?? 0)." (baseline {$allowed}) — lower the baseline (see the class doc)";
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    /**
     * @return array<string, int> relative path (forward slashes) → number of DB:: query calls
     */
    private function scan(string $root): array
    {
        $found = [];
        foreach (self::ROOTS as $dir) {
            if (! is_dir($root.'/'.$dir)) {
                continue;
            }
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                if (! str_ends_with($path, '.php') || $path === 'tests/Unit/Architecture/NoDbFacadeQueriesTest.php') {
                    continue;
                }
                $code = $this->withoutComments((string) file_get_contents($file->getPathname()));
                $count = preg_match_all(self::PATTERN, $code);
                if ($count > 0) {
                    $found[$path] = $count;
                }
            }
        }

        return $found;
    }

    /** Comments and doc blocks do not count (examples in PHPDoc, commented-out legacy code). */
    private function withoutComments(string $code): string
    {
        $out = '';
        foreach (token_get_all($code) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $out .= is_array($token) ? $token[1] : $token;
        }

        return $out;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Before / after snapshot of admin screens and AJAX calls (to-do W15, booking-team change log `docs/booking-team-changes.md`).
 * Runs each request of a spec file as each user, inside a transaction that is rolled back, and stores the status and a
 * hash of the normalised body (CSRF tokens, times, random ids removed). With `--compare` it lists every request whose
 * status or body changed. Local environment and the `xlrm_testing` database only.
 *
 *   DB_DATABASE=xlrm_testing php artisan dev:route-snapshot 1,40 spec.txt after.json --compare=before.json
 *
 * Spec lines: `GET sales/booking/5/edit`, `POST sales/booking/search {"q":"x"}`, `AJAX GET sales/booking/get-do-amount?do_no=1`.
 */
class RouteSnapshot extends Command
{
    protected $signature = 'dev:route-snapshot {users : comma-separated user ids} {spec : spec file} {out : result JSON}
                            {--compare= : an earlier result JSON to compare with} {--save= : folder for the normalised bodies}';

    protected $description = 'Local only: snapshot admin responses (rolled back) and compare before / after a change';

    public function handle(Kernel $kernel): int
    {
        if (! app()->environment('local') || config('database.connections.mysql.database') !== 'xlrm_testing') {
            $this->error('Runs only in the local environment against xlrm_testing (DB_DATABASE=xlrm_testing).');

            return self::FAILURE;
        }

        $lines = array_values(array_filter(array_map('trim', (array) file((string) $this->argument('spec'))), fn ($l) => $l !== '' && $l[0] !== '#'));
        $out = [];
        $bad = 0;
        foreach (array_map('intval', explode(',', (string) $this->argument('users'))) as $userId) {
            foreach ($lines as $line) {
                [$key, $row] = $this->snapshot($kernel, $userId, $line);
                $out[$key] = $row;
                $bad += $row['flag'] ? 1 : 0;
                $this->line(str_pad((string) $row['code'], 4).' '.str_pad((string) $row['len'], 8).' '.$key.($row['flag'] ? '  <<< CHECK '.$row['head'] : ''));
            }
        }
        file_put_contents((string) $this->argument('out'), json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->info("bad: {$bad} of ".count($out));

        return $this->compare($out) ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{0: string, 1: array{code: int|string, len: int, hash: string, flag: bool, head: string}} */
    private function snapshot(Kernel $kernel, int $userId, string $line): array
    {
        $ajax = str_starts_with($line, 'AJAX ');
        $line = $ajax ? substr($line, 5) : $line;
        [$method, $rest] = explode(' ', $line, 2) + [1 => ''];
        [$url, $json] = array_pad(explode(' ', trim($rest), 2), 2, null);
        auth(backpack_guard_name())->loginUsingId($userId);
        DB::beginTransaction();
        try {
            $request = Request::create('/admin/'.ltrim((string) $url, '/'), $method, $json ? (json_decode($json, true) ?? []) : []);
            if ($ajax) {
                $request->headers->set('X-Requested-With', 'XMLHttpRequest');
                $request->headers->set('Accept', 'application/json');
            }
            $session = app('session')->driver();
            $session->start();
            $request->setLaravelSession($session);
            $request->merge(['_token' => $session->token()]);
            $response = $kernel->handle($request);
            $code = $response->getStatusCode();
            $body = $response->isRedirect()
                ? 'REDIRECT '.$response->headers->get('Location').' '.json_encode(session()->get('errors')?->all() ?? [])
                : (string) $response->getContent();
        } catch (Throwable $e) {
            $code = 'EXC';
            $body = $e::class.': '.$e->getMessage();
        } finally {
            DB::rollBack();
            app()->forgetInstance('auth');
            app()->forgetInstance('session');
            app()->forgetInstance('session.store');
        }
        $normal = $this->normalise($body);
        if ($dir = $this->option('save')) {
            @mkdir($dir, 0777, true);
            file_put_contents($dir.'/'.preg_replace('/[^A-Za-z0-9]+/', '_', "{$userId} {$method} {$url}").'.txt', str_replace('> <', ">\n<", $normal));
        }
        $flag = $code === 'EXC' || $code >= 500 || preg_match('/Undefined (variable|array key|property)|ErrorException|QueryException|SQLSTATE/', $body) === 1;

        return ["{$userId} {$method} {$url}", ['code' => $code, 'len' => strlen($body), 'hash' => md5($normal), 'flag' => $flag, 'head' => mb_substr(strip_tags($normal), 0, 200)]];
    }

    /** Remove what changes on every render: CSRF tokens, times, hashes, random element ids. */
    private function normalise(string $body): string
    {
        $patterns = [
            '/name="_token" value="[^"]+"/' => '', '/<meta name="csrf-token" content="[^"]+"/' => '', '/"csrf[_-]?token"\s*:\s*"[^"]+"/i' => '',
            '/\b\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})?\b/' => 'DT', '/\b\d{2}:\d{2}(:\d{2})?\b/' => 'TM',
            '/[?&](v|id)=[0-9a-f]{6,}/' => '', '/\b[0-9a-f]{32,40}\b/' => 'HASH', '/(xl|uid|id)-[A-Za-z0-9]{6,}/' => 'RID',
            '/dropdown-[A-Za-z0-9]{8}\b/' => 'dropdown-RID', '/\s+/' => ' ',
        ];

        return (string) preg_replace(array_keys($patterns), array_values($patterns), $body);
    }

    /** @param  array<string, array<string, mixed>>  $out */
    private function compare(array $out): bool
    {
        $file = $this->option('compare');
        if (! $file || ! is_file($file)) {
            return true;
        }
        $before = (array) json_decode((string) file_get_contents($file), true);
        $diff = 0;
        foreach ($out as $key => $row) {
            $was = $before[$key] ?? null;
            if ($was !== null && ($was['code'] !== $row['code'] || $was['hash'] !== $row['hash'])) {
                $diff++;
                $this->warn("DIFF {$key}  {$was['code']} → {$row['code']}  len {$was['len']} → {$row['len']}");
            }
        }
        $this->info('compared: '.count($out)." differences: {$diff}");

        return $diff === 0;
    }
}

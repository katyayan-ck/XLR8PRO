<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Fast replacement for the per-process HTTP smoke: every parameter-free admin GET screen is
 * requested as superadmin (must not 5xx) and as a non-superadmin user (200/302/403/404).
 * Opt-in (excluded from the default suite in phpunit.xml): the sweep makes ~350 requests
 * and takes a few minutes, so run it before merges, not after every change.
 *
 *   php artisan test --group=smoke
 *   SMOKE_REPORT=1 php artisan test --group=smoke   (prints every status)
 */
#[Group('smoke')]
class AdminScreenSmokeTest extends TestCase
{
    use DatabaseTransactions;

    /** Screens that 500 today for known, tracked reasons (all hidden from the menu). */
    private const KNOWN_BROKEN = [
        'finance/import' => 'needs local gscreds.json (Google credentials)',
        'insurance/import' => 'needs local gscreds.json (Google credentials)',
        'rto/import' => 'needs local gscreds.json (Google credentials)',
        'sales/booking/reports/branch-booking' => 'BUG-122 missing report tables',
        'sales/booking/reports/consolidated-booking' => 'BUG-122',
        'sales/booking/reports/live-order' => 'BUG-122',
        'sales/booking/reports/live-order/list' => 'BUG-122',
        'sales/booking/reports/pending-actions' => 'BUG-122',
        'sales/booking/reports/pending-actions/list' => 'BUG-122',
        'sales/booking/reports/stock' => 'BUG-122',
        'sales/booking/reports/stock/list' => 'BUG-122',
        'spares/spare-request' => 'BUG-030/032 spares module',
        'spares/spare-request/create' => 'BUG-030/032 spares module',
    ];

    /** @return list<string> */
    private function screens(): array
    {
        $paths = [];
        foreach ($this->app['router']->getRoutes() as $route) {
            $uri = $route->uri();
            if (! in_array('GET', $route->methods(), true) || ! str_starts_with($uri, 'admin') || str_contains($uri, '{')) {
                continue;
            }
            if (preg_match('#(export|download|template|logout|/search|ajax|get-|/data$|/status|__)#', $uri)) {
                continue;
            }
            $paths[] = ltrim(substr($uri, strlen('admin')), '/');
        }
        sort($paths);

        return array_values(array_unique($paths));
    }

    /** @return array<string, int> path => status */
    private function sweep(User $user): array
    {
        $statuses = [];
        foreach ($this->screens() as $path) {
            $this->app['auth']->guard('backpack')->logout();
            $this->actingAs($user, 'backpack');
            $statuses[$path] = $this->get('/admin/'.$path)->getStatusCode();
        }

        return $statuses;
    }

    public function test_admin_screens_do_not_error(): void
    {
        $superadmin = User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))->firstOrFail();
        $scoped = User::where('is_active', 1)->whereHas('roles')->get()->first(fn (User $u) => ! $u->isSuperAdmin());

        $failures = [];
        $report = [];
        foreach ($this->sweep($superadmin) as $path => $status) {
            $report[] = sprintf('%-45s superadmin %d', $path, $status);
            if ($status >= 500 && ! isset(self::KNOWN_BROKEN[$path])) {
                $failures[] = "superadmin {$status} /admin/{$path}";
            }
        }
        if ($scoped) {
            foreach ($this->sweep($scoped) as $path => $status) {
                $report[] = sprintf('%-45s user#%d %d', $path, $scoped->id, $status);
                // 404 = screen needs a reference (e.g. quotation/create without an enquiry).
                if (! in_array($status, [200, 302, 403, 404], true) && ! isset(self::KNOWN_BROKEN[$path])) {
                    $failures[] = "user #{$scoped->id} {$status} /admin/{$path}";
                }
            }
        }

        if (getenv('SMOKE_REPORT')) {
            fwrite(STDERR, implode(PHP_EOL, $report).PHP_EOL);
        }

        $this->assertSame([], $failures, implode(PHP_EOL, $failures));
    }
}

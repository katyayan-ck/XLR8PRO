<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\DashboardPeriod;
use App\Services\Dashboard\DashboardService;
use App\Support\Facades\DataScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Dynamic dashboard (DEC-072): shows the widgets from config/dashboard.php that the user's permissions allow; each
 * widget's numbers are fetched separately (JSON), computed inside the user's data scope and cached per user + scope +
 * period. Designations are never named — permissions decide what appears.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(Request $request): View
    {
        $user = backpack_user();
        abort_unless($user, 403);
        if (! $user->can('admin.dashboard')) {
            abort(403, 'Unauthorized. You do not have permission to view the dashboard.');
        }

        $period = DashboardPeriod::make($request->query('period'));
        $groups = [];
        foreach ($this->allowedWidgets() as $key => $widget) {
            $groups[$widget['group']][$key] = $widget;
        }

        return view('admin.dashboard.index', [
            'title' => 'Dashboard',
            'period' => $period,
            'periods' => DashboardPeriod::KEYS,
            'groupNames' => (array) config('dashboard.groups', []),
            'groups' => $groups,
            'user' => $user,
        ]);
    }

    public function widget(Request $request, string $key): JsonResponse
    {
        $user = backpack_user();
        abort_unless($user && $user->can('admin.dashboard'), 403);

        $widget = config("dashboard.widgets.{$key}");
        abort_unless(is_array($widget), 404);
        if (! empty($widget['permission']) && ! $user->can($widget['permission'])) {
            abort(403, 'Unauthorized.');
        }

        $period = DashboardPeriod::make($request->query('period'));
        $cacheKey = "dashboard:{$key}:{$user->id}:".DataScope::current()->hash().":{$period->key}:".$period->from->toDateString();
        $data = Cache::remember($cacheKey, (int) config('dashboard.cache_seconds', 300),
            fn () => $this->dashboard->{$widget['method']}($period, $user));

        return response()->json(['ok' => true, 'key' => $key, 'type' => $widget['type'], 'period' => $period->label(), 'data' => $data]);
    }

    /** @return array<string, array<string, mixed>> */
    private function allowedWidgets(): array
    {
        $user = backpack_user();

        return array_filter((array) config('dashboard.widgets', []),
            fn (array $w) => empty($w['permission']) || $user->can($w['permission']));
    }
}

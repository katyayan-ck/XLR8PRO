<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * See known-bugs-report.md BUG-044: this route had no authentication or
 * authorization of any kind (routes/web.php previously registered it with
 * only the default 'web' middleware) — fixed by adding the standard
 * 'web' + 'admin' (CheckIfAdmin) middleware and a performance.view
 * permission check (newly minted — no existing permission covered this
 * domain).
 */
class PerformanceController extends Controller
{
    public function report(Request $request)
    {
        if (! backpack_user()->can('performance.view')) {
            abort(403, 'Unauthorized. You do not have permission to view performance reports.');
        }

        $user = auth()->user();
        $topic = $request->topic ?? 'sales';
        $combo = $request->combo ?? ['branch' => 'Bkn', 'segment' => 'Personal', 'subsegment' => 'Non-XUV'];
        $metric = $request->metric ?? 'count';
        $from = $request->from;
        $to = $request->to;

        $data = $user->aggregatePerformance($topic, $combo, $metric, $from, $to);

        return view('performance_report', compact('data'));
    }
}

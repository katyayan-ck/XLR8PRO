<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PerformanceController extends Controller
{
    public function report(Request $request)
    {
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

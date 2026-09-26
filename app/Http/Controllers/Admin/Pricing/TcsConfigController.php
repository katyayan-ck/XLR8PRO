<?php

namespace App\Http\Controllers\Admin\Pricing;

use App\Http\Controllers\Controller;
use App\Models\Vehicle\Pricing\TcsConfig;
use App\Services\Vehicle\Pricing\Rules\TcsConfigService;
use Illuminate\Http\Request;

class TcsConfigController extends Controller
{
    public function index()
    {
        if (! backpack_user()->can('PRC_TCS_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view TCS configuration.');
        }

        $tcs = TcsConfig::current();

        return view('admin.pricing.tcs.index', [
            'title' => 'TCS Configuration',
            'tcs' => $tcs,
        ]);
    }

    public function update(Request $request)
    {
        if (! backpack_user()->can('PRC_TCS_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to update TCS configuration.');
        }

        // Field rules and the single-active-row rule live in TcsConfigService (DEC-056).
        app(TcsConfigService::class)->saveCurrent([
            'limit_amount' => $request->input('limit_amount'),
            'rate_pct' => $request->input('rate_pct'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        \Alert::success('TCS configuration updated successfully.')->flash();

        return redirect()->route('pricing.tcs.index');
    }
}

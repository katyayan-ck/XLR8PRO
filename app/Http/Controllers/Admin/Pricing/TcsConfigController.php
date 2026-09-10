<?php

namespace App\Http\Controllers\Admin\Pricing;

use App\Http\Controllers\Controller;
use App\Models\Vehicle\Pricing\TcsConfig;
use Illuminate\Http\Request;

class TcsConfigController extends Controller
{
    public function index()
    {
        $tcs = TcsConfig::current();
        return view('admin.pricing.tcs.index', [
            'title' => 'TCS Configuration',
            'tcs'   => $tcs,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'limit_amount' => 'required|numeric|min:0',
            'rate_pct'     => 'required|numeric|min:0|max:100',
            'is_active'    => 'nullable|boolean',
        ]);

        $tcs = TcsConfig::current();
        if (!$tcs->exists) {
            $tcs = new TcsConfig();
        }

        TcsConfig::where('is_active', true)->update(['is_active' => false]);

        $tcs->fill([
            'limit_amount' => $validated['limit_amount'],
            'rate_pct'     => $validated['rate_pct'],
            'is_active'    => $request->boolean('is_active', true),
        ]);
        $tcs->save();

        \Alert::success('TCS configuration updated successfully.')->flash();
        return redirect()->route('pricing.tcs.index');
    }
}
<?php

namespace App\Http\Controllers\Admin\Pricing;

use App\Http\Controllers\Controller;
use App\Models\Vehicle\Pricing\Hold;
use Illuminate\Http\Request;

class HoldController extends Controller
{
    public function index()
    {
        if (! backpack_user()->can('PRC_HOLD_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view price holds.');
        }

        $holds = Hold::orderBy('scope')->get()->keyBy('scope');
        $scopes = ['ALL', 'PV', 'CV', 'LMM', 'BEV', 'CSD', 'TAXI'];

        return view('admin.pricing.hold.index', [
            'title' => 'Price Hold Management',
            'holds' => $holds,
            'scopes' => $scopes,
        ]);
    }

    public function hold(Request $request)
    {
        if (! backpack_user()->can('PRC_HOLD_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to manage price holds.');
        }

        $request->validate([
            'scope' => 'required|string',
            'reason' => 'nullable|string|max:255',
        ]);

        Hold::putOnHold($request->scope, $request->reason);
        \Alert::success("Pricelist [{$request->scope}] has been put on Hold.")->flash();

        return redirect()->route('pricing.hold.index');
    }

    public function reopen(Request $request)
    {
        if (! backpack_user()->can('PRC_HOLD_MANAGE')) {
            abort(403, 'Unauthorized. You do not have permission to manage price holds.');
        }

        $request->validate([
            'scope' => 'required|string',
            'reason' => 'nullable|string|max:255',
        ]);

        Hold::reopen($request->scope, $request->reason);
        \Alert::success("Pricelist [{$request->scope}] has been Reopened.")->flash();

        return redirect()->route('pricing.hold.index');
    }
}

<?php

namespace App\Http\Controllers\Admin\Pricing;

use App\Http\Controllers\Controller;
use App\Models\Vehicle\Pricing\Hold;
use Illuminate\Http\Request;

class HoldController extends Controller
{
    /** Holds are managed on Settings → Pricing now (DEC-091); the old address leads there. */
    public function index()
    {
        if (! backpack_user()->can('PRC_HOLD_VIEW')) {
            abort(403, 'Unauthorized. You do not have permission to view price holds.');
        }

        return redirect()->route('utils.settings.index', ['tab' => 'pricing']);
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
        \Alert::success(__('pricing.flash.pricelist_put_on_hold', ['scope' => $request->scope]))->flash();

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
        \Alert::success(__('pricing.flash.pricelist_reopened', ['scope' => $request->scope]))->flash();

        return redirect()->route('pricing.hold.index');
    }
}

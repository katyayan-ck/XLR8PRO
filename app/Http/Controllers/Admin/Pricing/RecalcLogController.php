<?php

namespace App\Http\Controllers\Admin\Pricing;

use App\Http\Controllers\Controller;
use App\Models\Vehicle\Pricing\RecalcRun;
use App\Services\Vehicle\Pricing\Engine\PricingRecalcService;
use App\Services\Vehicle\Pricing\PricingSyncStamp;
use Illuminate\View\View;

/** Automatic recalculation runs (DEC-083): what triggered each, how many vehicles it republished, failures. */
class RecalcLogController extends Controller
{
    public function index(PricingRecalcService $recalc, PricingSyncStamp $stamp): View
    {
        if (! backpack_user()->can('PRC_RCLC_VIEW')) {
            abort(403, 'You do not have permission to view the recalculation log.');
        }

        return view('admin.pricing.recalc-log', [
            'title' => 'Pricing Recalculation Log',
            'runs' => RecalcRun::query()->latest('id')->paginate(25),
            'pending' => count($recalc->pending()),
            'stamp' => $stamp->lastUpdated(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\PricingService;

class PricingApiController extends Controller
{
    protected PricingService $pricingService;

    public function __construct(PricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    public function calculateExchange(Request $request)
    {
        $request->validate([
            'expected_price' => 'required|numeric|min:0',
            'offered_price'  => 'required|numeric|min:0',
            'exchange_bonus' => 'nullable|numeric|min:0',
        ]);

        $gap = $this->pricingService->calculateExchangeGap(
            (float) $request->expected_price,
            (float) $request->offered_price,
            (float) ($request->exchange_bonus ?? 0)
        );

        return response()->json(['success' => true, 'difference' => $gap]);
    }
}

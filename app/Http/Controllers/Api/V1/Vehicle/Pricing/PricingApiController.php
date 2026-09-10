<?php

namespace App\Http\Controllers\Api\V1\Vehicle\Pricing;

use App\Http\Controllers\Controller;
use App\Services\Vehicle\Pricing\PricingEngineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PricingController extends Controller
{
    public function __construct(protected PricingEngineService $pricingEngine) {}

    /**
     * @OA\Get(
     *   path="/api/v1/vehicle/pricing/{modelCode}",
     *   tags={"Pricing"},
     *   summary="Get on-road pricing JSON for an OEM Code",
     *   @OA\Parameter(name="modelCode", in="path", required=true, @OA\Schema(type="string")),
     *   @OA\Parameter(name="permit", in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="vin_type", in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="channel", in="query", @OA\Schema(type="string")),
     *   @OA\Response(response=200, description="Fixed-key pricing JSON")
     * )
     */
    public function getPricing(Request $request, ?string $modelCode = null)
    {
        $modelCode = $modelCode
            ?? $request->input('model_code')
            ?? $request->input('oem_code')
            ?? $request->input('oemcode');

        if (empty($modelCode)) {
            return response()->json(['success' => false, 'message' => 'model_code / oem_code is required'], 422);
        }

        try {
            $payload = $this->pricingEngine->getPricingPayload($modelCode, [
                'permit'   => $request->input('permit'),
                'vin_type' => $request->input('vin_type', 'nv'),
                'channel'  => $request->input('channel', 'normal'),
                'wef_date' => $request->input('wef_date'),
            ]);

            if (! empty($payload['hold'])) {
                return response()->json([
                    'success' => false,
                    'message' => $payload['errors'][0] ?? 'Price list on hold',
                    'data'    => $payload,
                ], 423);
            }

            return response()->json([
                'success' => empty($payload['errors']),
                'data'    => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Pricing API] failed', ['model_code' => $modelCode, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Unable to calculate pricing: ' . $e->getMessage()], 500);
        }
    }

    public function getLivePricing(Request $request, string $modelCode)
    {
        return $this->getPricing($request, $modelCode);
    }
}

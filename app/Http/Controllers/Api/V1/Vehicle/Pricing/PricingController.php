<?php

namespace App\Http\Controllers\Api\V1\Vehicle\Pricing;

use App\Enums\ErrorCodeEnum;
use App\Http\Controllers\BaseController;
use App\Services\Vehicle\Pricing\Engine\PricingQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * getPricing for the mobile app (DEC-073 step 11 / DEC-080): the published price of a vehicle as the fixed-key contract
 * v2, with the caller's selections applied. 404 when nothing is published, 423 when the price list is on hold.
 */
class PricingController extends BaseController
{
    public function __construct(private readonly PricingQueryService $pricing) {}

    /**
     * @OA\Get(
     *   path="/api/v1/vehicle/pricing/{oemCode}",
     *   tags={"Pricing"},
     *   summary="Published on-road pricing (contract v2) for a full OEM code, with optional selections",
     *   security={{"sanctum":{}}},
     *
     *   @OA\Parameter(name="oemCode", in="path", required=true, @OA\Schema(type="string"), description="Full OEM code (with colour)"),
     *   @OA\Parameter(name="permit", in="query", @OA\Schema(type="string", example="PRIVATE")),
     *   @OA\Parameter(name="vin_type", in="query", @OA\Schema(type="string", enum={"NV","OV"}, default="NV")),
     *   @OA\Parameter(name="channel", in="query", @OA\Schema(type="string", enum={"normal","csd"}, default="normal")),
     *   @OA\Parameter(name="wef_date", in="query", @OA\Schema(type="string", format="date")),
     *   @OA\Parameter(name="rsa_years", in="query", @OA\Schema(type="integer")),
     *   @OA\Parameter(name="shield_scheme", in="query", @OA\Schema(type="integer")),
     *   @OA\Parameter(name="insurance[company]", in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="insurance[plan]", in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="insurance[addons][]", in="query", @OA\Schema(type="array", @OA\Items(type="string"))),
     *   @OA\Parameter(name="reg_type", in="query", @OA\Schema(type="string", enum={"Regular","BH"})),
     *   @OA\Parameter(name="outside_state", in="query", @OA\Schema(type="boolean")),
     *   @OA\Parameter(name="include_cod", in="query", @OA\Schema(type="boolean")),
     *   @OA\Parameter(name="exchange", in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="corporate", in="query", @OA\Schema(type="string")),
     *   @OA\Parameter(name="loyalty", in="query", @OA\Schema(type="string")),
     *
     *   @OA\Response(response=200, description="data.pricing = fixed-key contract v2"),
     *   @OA\Response(response=404, description="PRICING_NOT_FOUND"),
     *   @OA\Response(response=423, description="PRICING_ON_HOLD (data.pricing with hold = true)")
     * )
     */
    public function show(Request $request, string $oemCode): JsonResponse
    {
        try {
            $options = $request->validate([
                'permit' => ['nullable', 'string', 'max:30'],
                'vin_type' => ['nullable', Rule::in(['NV', 'OV', 'nv', 'ov'])],
                'channel' => ['nullable', Rule::in(['normal', 'csd'])],
                'wef_date' => ['nullable', 'date'],
                'rsa_years' => ['nullable', 'integer', 'min:0', 'max:10'],
                'shield_scheme' => ['nullable', 'integer', 'min:0', 'max:10'],
                'insurance' => ['nullable', 'array'],
                'insurance.company' => ['nullable', 'string', 'max:40'],
                'insurance.plan' => ['nullable', 'string', 'max:20'],
                'insurance.addons' => ['nullable', 'array'],
                'insurance.addons.*' => ['string', 'max:40'],
                'reg_type' => ['nullable', Rule::in(['Regular', 'BH', 'REGULAR', 'bh', 'regular'])],
                'outside_state' => ['nullable', 'boolean'],
                'include_cod' => ['nullable', 'boolean'],
                'exchange' => ['nullable', 'string', 'max:60'],
                'corporate' => ['nullable', 'string', 'max:60'],
                'loyalty' => ['nullable', 'string', 'max:60'],
            ]);
            $result = $this->pricing->getPricing($oemCode, array_filter($options, fn ($v) => $v !== null));

            return match ($result->code) {
                'NOT_FOUND' => $this->errorResponse($result->message, ErrorCodeEnum::PRICING_NOT_FOUND->value, 404),
                'ON_HOLD' => $this->errorResponse($result->message, ErrorCodeEnum::PRICING_ON_HOLD->value, 423, [], $result->data),
                default => $this->successResponse($result->data, $result->message),
            };
        } catch (Throwable $e) {
            return $this->handleException($e, 'Pricing lookup', ['oem_code' => $oemCode]);
        }
    }
}

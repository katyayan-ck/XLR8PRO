<?php

namespace App\Http\Controllers\Api\V1\Vehicle;

use App\Enums\ErrorCodeEnum;
use App\Http\Controllers\BaseController;
use App\Services\Vehicle\Content\CompareService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vehicle compare for the app (DEC-092 Phase 4): trims of one model by features, models of one segment by
 * specifications — the same data as the admin screen (CompareService). Needs VEH_CMPR_VIEW.
 *
 * @OA\Get(path="/vehicles/compare/variants", operationId="compareVariants", tags={"Vehicles"}, summary="Compare trims of one model by features",
 *     security={{"sanctum":{}}},
 *
 *     @OA\Parameter(name="model", in="query", required=true, @OA\Schema(type="string")),
 *     @OA\Parameter(name="variants[]", in="query", required=true, @OA\Schema(type="array", @OA\Items(type="string"))),
 *     @OA\Parameter(name="only_differences", in="query", @OA\Schema(type="boolean")),
 *
 *     @OA\Response(response=200, description="Envelope; data = {kind, model, columns, groups}"),
 *     @OA\Response(response=422, description="VEHICLE_COMPARE_SELECTION")
 * )
 *
 * @OA\Get(path="/vehicles/compare/models", operationId="compareModels", tags={"Vehicles"}, summary="Compare models of one segment by specifications",
 *     security={{"sanctum":{}}},
 *
 *     @OA\Parameter(name="models[]", in="query", required=true, @OA\Schema(type="array", @OA\Items(type="string"))),
 *     @OA\Parameter(name="only_differences", in="query", @OA\Schema(type="boolean")),
 *
 *     @OA\Response(response=200, description="Envelope; data = {kind, segment, columns, groups}"),
 *     @OA\Response(response=422, description="VEHICLE_COMPARE_SEGMENT or VEHICLE_COMPARE_SELECTION")
 * )
 */
class CompareController extends BaseController
{
    public function __construct(private readonly CompareService $compare) {}

    public function variants(Request $request): JsonResponse
    {
        $this->allow($request);
        $data = $request->validate(['model' => 'required|string|max:50', 'variants' => 'required|array|max:'.CompareService::MAX,
            'variants.*' => 'string|max:50', 'only_differences' => 'nullable|boolean']);

        return $this->respond($this->compare->variants($data['model'], $data['variants'], (bool) ($data['only_differences'] ?? false)));
    }

    public function models(Request $request): JsonResponse
    {
        $this->allow($request);
        $data = $request->validate(['models' => 'required|array|max:'.CompareService::MAX, 'models.*' => 'string|max:50',
            'only_differences' => 'nullable|boolean']);

        return $this->respond($this->compare->models($data['models'], (bool) ($data['only_differences'] ?? false)));
    }

    private function allow(Request $request): void
    {
        if (! $request->user()?->can('VEH_CMPR_VIEW')) {
            abort(403);
        }
    }

    private function respond(Result $result): JsonResponse
    {
        if ($result->ok) {
            return $this->successResponse($result->data, 'Comparison ready', 200);
        }
        $status = ErrorCodeEnum::tryFrom($result->code)?->statusCode() ?? 422;

        return $this->errorResponse($result->message, $result->code, $status, [], $result->data ?: null);
    }
}

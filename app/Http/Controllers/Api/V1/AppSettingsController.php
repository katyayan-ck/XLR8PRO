<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Services\Platform\Settings\SettingsCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * App settings for the mobile app (DEC-091, W13 Phase 6): dealership branding, channel switches, which profile fields
 * the user may change, display formats and the pricing sync stamp — the same values as Utilities → Settings, read live.
 *
 * @OA\Get(
 *     path="/app-settings",
 *     operationId="getAppSettings",
 *     tags={"System Settings"},
 *     summary="Settings the app needs (branding, switches, editable profile fields)",
 *     security={{"sanctum":{}}},
 *
 *     @OA\Response(response=200, description="Standard envelope; data = {dealership, channels, account, ui, pricing_last_updated_at}"),
 *     @OA\Response(response=401, description="Unauthorized")
 * )
 */
class AppSettingsController extends BaseController
{
    public function __construct(private readonly SettingsCatalogue $catalogue) {}

    public function show(Request $request): JsonResponse
    {
        return $this->successResponse($this->catalogue->appSettings($request->user()), 'App settings retrieved', 200);
    }
}

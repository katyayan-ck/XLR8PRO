<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ErrorCodeEnum;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ResourceNotFoundException;
use App\Exceptions\ValidationException;
use App\Http\Controllers\BaseController;
use App\Models\Utilities\Settings\SystemSetting;
use App\Services\SystemSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SystemSettingApiController
 * 
 * Provides REST API endpoints for managing system settings and configuration.
 * Allows retrieval and management of application-wide settings organized by topics.
 * 
 * Features:
 * - List all system settings organized by topic/category
 * - Retrieve settings by topic (e.g., site, dealership, pricing)
 * - Get individual setting by key
 * - Update settings (admin only)
 * - Export settings as JSON
 * - Import settings from JSON
 * - Comprehensive error handling with standard error codes
 * 
 * @category API Controllers
 * @package App\Http\Controllers\Api\V1
 * @author VDMS Development Team
 * @version 2.0
 */
class SystemSettingApiController extends BaseController
{
    protected SystemSettingService $settingService;

    public function __construct(SystemSettingService $settingService)
    {
        $this->settingService = $settingService;
        $this->middleware('auth:sanctum');
    }

    /**
     * Get all system settings
     *
     * Retrieves all active system settings organized by topic/category.
     *
     * @OA\Get(
     *     path="/system-settings",
     *     operationId="getAllSystemSettings",
     *     tags={"System Settings"},
     *     summary="Get all system settings",
     *     description="Retrieve all settings grouped by topic",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Settings retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Settings retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="site", type="object",
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="logo", type="string")
     *                 )
     *             ),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $settings = $this->settingService->allByTopic();
            return $this->successResponse($settings, 'Settings retrieved successfully', 200);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Get All Settings', ['user_id' => Auth::id()]);
        }
    }

    /**
     * Get settings by topic
     *
     * Retrieves settings for a specific topic/category.
     *
     * @OA\Get(
     *     path="/system-settings/topic/{topic}",
     *     operationId="getSettingsByTopic",
     *     tags={"System Settings"},
     *     summary="Get settings by topic",
     *     description="Retrieve settings for specific topic",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="topic",
     *         in="path",
     *         required=true,
     *         description="Settings topic (e.g., site, dealership)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Settings retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Settings for topic retrieved"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Topic not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param string $topic
     * @return JsonResponse
     */
    public function getByTopic(string $topic): JsonResponse
    {
        try {
            $settings = $this->settingService->topic($topic);
            if (empty($settings)) {
                return $this->notFoundResponse('Topic', $topic);
            }
            return $this->successResponse($settings, "Settings for topic '{$topic}' retrieved", 200);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Get Settings By Topic', ['topic' => $topic, 'user_id' => Auth::id()]);
        }
    }

    /**
     * Get single setting
     *
     * Retrieves a single setting by key.
     *
     * @OA\Get(
     *     path="/system-settings/{key}",
     *     operationId="getSetting",
     *     tags={"System Settings"},
     *     summary="Get single setting",
     *     description="Retrieve setting by key",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="key",
     *         in="path",
     *         required=true,
     *         description="Setting key (e.g., site.name)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Setting retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Setting retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="key", type="string"),
     *                 @OA\Property(property="value", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Setting not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param string $key
     * @return JsonResponse
     */
    public function show(string $key): JsonResponse
    {
        try {
            $setting = $this->settingService->getSetting($key);
            if (!$setting) {
                return $this->notFoundResponse('Setting', $key);
            }
            return $this->successResponse($setting, 'Setting retrieved successfully', 200);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Get Setting', ['key' => $key, 'user_id' => Auth::id()]);
        }
    }

    /**
     * Update setting
     *
     * Updates a single setting value.
     * Requires admin permission.
     *
     * @OA\Put(
     *     path="/system-settings/{key}",
     *     operationId="updateSetting",
     *     tags={"System Settings"},
     *     summary="Update setting",
     *     description="Update setting value",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="key",
     *         in="path",
     *         required=true,
     *         description="Setting key",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"value"},
     *             @OA\Property(property="value", type="string", example="new value")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Setting updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Setting updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid input"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Setting not found"),
     *     @OA\Response(response=500, description="Internal server error")
     *     )
     *
     * @param Request $request
     * @param string $key
     * @return JsonResponse
     */
    public function update(Request $request, string $key): JsonResponse
    {
        try {
            $validated = $request->validate([
                'value' => 'required',
            ]);

            $setting = $this->settingService->getSetting($key);
            if (!$setting) {
                return $this->notFoundResponse('Setting', $key);
            }

            $this->authorize('update', SystemSetting::class);

            $updated = $this->settingService->set($key, $validated['value'], $setting->topic);

            $this->logAudit('update', 'SystemSetting', ['old' => $setting->value, 'new' => $validated['value']], $setting->id, 'success');

            return $this->successResponse($updated, 'Setting updated successfully', 200);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Update Setting', ['key' => $key, 'user_id' => Auth::id()]);
        }
    }

    /**
     * Export settings
     *
     * Exports all settings as JSON.
     *
     * @OA\Get(
     *     path="/system-settings/export",
     *     operationId="exportSettings",
     *     tags={"System Settings"},
     *     summary="Export all settings",
     *     description="Export settings as JSON",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Settings exported",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @return JsonResponse
     */
    public function exportSettings(): JsonResponse
    {
        try {
            $settings = $this->settingService->all();
            return $this->successResponse($settings, 'Settings exported successfully', 200);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Export Settings', ['user_id' => Auth::id()]);
        }
    }

    /**
     * Import settings
     *
     * Imports settings from JSON array.
     * Requires admin permission.
     *
     * @OA\Post(
     *     path="/system-settings/import",
     *     operationId="importSettings",
     *     tags={"System Settings"},
     *     summary="Import settings",
     *     description="Import settings from JSON",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="array", @OA\Items(type="object"))
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Import completed",
     *         @OA\JsonContent(
     *             @OA\Property(property="imported", type="integer"),
     *             @OA\Property(property="failed", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function importSettings(Request $request): JsonResponse
    {
        try {
            $this->authorize('import', SystemSetting::class);

            $settings = $request->json()->all();
            $imported = 0;
            $failed = 0;
            $errors = [];

            foreach ($settings as $setting) {
                try {
                    $this->settingService->set($setting['key'], $setting['value'], $setting['topic'] ?? null);
                    $imported++;
                } catch (Throwable $e) {
                    $failed++;
                    $errors[] = [
                        'key' => $setting['key'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
                    Log::error('Failed to import setting', [
                        'key' => $setting['key'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Settings import completed', [
                'imported' => $imported,
                'failed' => $failed,
                'user_id' => Auth::id(),
            ]);

            $this->logAudit(
                'import',
                'SystemSetting',
                ['imported' => $imported, 'failed' => $failed],
                null,
                $failed === 0 ? 'success' : 'partial'
            );

            return $this->successResponse(
                [
                    'imported' => $imported,
                    'failed' => $failed,
                    'errors' => !empty($errors) ? $errors : null,
                ],
                'Import completed',
                200
            );
        } catch (Throwable $e) {
            return $this->handleException($e, 'Import Settings', [
                'user_id' => Auth::id(),
            ]);
        }
    }
}

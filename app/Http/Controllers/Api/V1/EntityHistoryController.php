<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Services\EntityHistoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Utilities\CommHistory\CommThread;
use Throwable;

/**
 * @OA\Tag(
 *     name="Entity History",
 *     description="Endpoints for managing entity history, status, and communications"
 * )
 */
class EntityHistoryController extends BaseController
{
    protected $historyService;

    public function __construct(EntityHistoryService $historyService)
    {
        $this->historyService = $historyService;
    }

    /**
     * Get entity history
     *
     * Retrieves full history including status and threaded communications for an entity.
     *
     * @OA\Get(
     *     path="/history/{entityType}/{entityId}",
     *     operationId="getEntityHistory",
     *     tags={"Entity History"},
     *     summary="Get entity history",
     *     description="Retrieve status and communication threads for an entity",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="entityType",
     *         in="path",
     *         required=true,
     *         description="Entity type (e.g., Booking, Quote)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="entityId",
     *         in="path",
     *         required=true,
     *         description="Entity ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="History retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="History retrieved"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="History not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param string $entityType
     * @param int $entityId
     * @return JsonResponse
     */
    public function getHistory(string $entityType, int $entityId): JsonResponse
    {
        try {
            $entity = app("App\\Models\\{$entityType}")->findOrFail($entityId);
            $master = $entity->commMaster;
            if (!$master) {
                return $this->notFoundResponse('History');
            }
            $threads = $master->rootThreads()->with('children', 'media', 'actor', 'action')->get();
            return $this->successResponse($threads, 'History retrieved');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Get History', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
        }
    }

    /**
     * Add thread/comment to entity history
     *
     * Adds a new communication thread or reply to an entity's history.
     *
     * @OA\Post(
     *     path="/history/{entityType}/{entityId}/thread",
     *     operationId="addEntityThread",
     *     tags={"Entity History"},
     *     summary="Add comment to entity history",
     *     description="Add a new thread or reply with optional attachments",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="entityType",
     *         in="path",
     *         required=true,
     *         description="Entity type (e.g., Booking, Quote)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="entityId",
     *         in="path",
     *         required=true,
     *         description="Entity ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"action_key"},
     *             @OA\Property(property="action_key", type="string", example="remarked"),
     *             @OA\Property(property="title", type="string", nullable=true),
     *             @OA\Property(property="message", type="string", nullable=true),
     *             @OA\Property(property="parent_id", type="integer", nullable=true),
     *             @OA\Property(property="attachments", type="array", @OA\Items(type="string"), nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Comment added",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=201),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S201"),
     *             @OA\Property(property="message", type="string", example="Comment added"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid input"),
     *     @OA\Response(response=404, description="Entity not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @param string $entityType
     * @param int $entityId
     * @return JsonResponse
     */
    public function addThread(Request $request, string $entityType, int $entityId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'action_key' => 'required|string',
                'title' => 'string|nullable',
                'message' => 'string|nullable',
                'parent_id' => 'integer|nullable',
                'attachments' => 'array',
                'attachments.*' => 'file',
            ]);

            $entity = app("App\\Models\\{$entityType}")->findOrFail($entityId);
            $master = $entity->commMaster ?? $this->historyService->createMaster($entity, $entity->title ?? 'Entity History', null, 'active');

            $parent = $validated['parent_id'] ? CommThread::findOrFail($validated['parent_id']) : null;

            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = ['path' => $file->store('attachments')];
                }
            }

            $thread = $this->historyService->addThread(
                $master,
                $validated['action_key'],
                $validated['title'] ?? null,
                $validated['message'] ?? null,
                $attachments,
                $parent
            );

            return $this->successResponse($thread, 'Comment added', 201);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Add Comment', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
        }
    }

    /**
     * Create chat (group or one-to-one)
     *
     * Creates a new chat without an underlying entity.
     *
     * @OA\Post(
     *     path="/chats",
     *     operationId="createChat",
     *     tags={"Entity History"},
     *     summary="Create group or one-to-one chat",
     *     description="Create a chat with participants and history mechanism",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type","participants","title"},
     *             @OA\Property(property="type", type="string", enum={"group","one_to_one"}),
     *             @OA\Property(property="participants", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Chat created",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=201),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S201"),
     *             @OA\Property(property="message", type="string", example="Chat created"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid input"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createChat(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => 'required|in:group,one_to_one',
                'participants' => 'required|array|min:2',
                'participants.*' => 'integer|exists:users,id',
                'title' => 'required|string',
                'description' => 'string|nullable',
            ]);

            $master = $this->historyService->createChatMaster(
                $validated['type'],
                $validated['participants'],
                $validated['title'],
                $validated['description']
            );

            return $this->successResponse($master, 'Chat created', 201);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Create Chat');
        }
    }
}

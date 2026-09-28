<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Services\DocService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Throwable;
use Illuminate\Support\Facades\Auth;
use App\Models\Utilities\Docs\Document;
use App\Models\Utilities\Docs\DocGroup;

/**
 * @OA\Tag(
 *     name="Documents",
 *     description="Endpoints for managing documents"
 * )
 */
class DocController extends BaseController
{
    protected $docService;

    public function __construct(DocService $docService)
    {
        $this->docService = $docService;
    }

    /**
     * Upload document
     *
     * Uploads a new document, either standalone or bound to an entity.
     * Supports AI tagging if enabled in settings.
     *
     * @OA\Post(
     *     path="/docs/upload",
     *     operationId="uploadDocument",
     *     tags={"Documents"},
     *     summary="Upload document",
     *     description="Upload standalone or entity-bound document",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"title","file"},
     *                 @OA\Property(property="title", type="string", example="Contract Document"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Annual contract"),
     *                 @OA\Property(property="category_key", type="string", example="contracts"),
     *                 @OA\Property(property="expiry_date", type="string", format="date", nullable=true, example="2026-12-31"),
     *                 @OA\Property(property="file", type="file", description="Document file"),
     *                 @OA\Property(property="entity_type", type="string", nullable=true, example="Branch"),
     *                 @OA\Property(property="entity_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="requires_approval", type="boolean", default=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Document uploaded",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=201),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S201"),
     *             @OA\Property(property="message", type="string", example="Document uploaded"),
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
    public function upload(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string',
                'description' => 'string|nullable',
                'category_key' => 'required|string',
                'expiry_date' => 'date|nullable',
                'file' => 'required|file',
                'entity_type' => 'string|nullable',
                'entity_id' => 'integer|nullable',
                'requires_approval' => 'boolean',
            ]);

            $entity = $validated['entity_type'] && $validated['entity_id']
                ? app("App\\Models\\{$validated['entity_type']}")->findOrFail($validated['entity_id'])
                : null;

            $doc = $this->docService->upload($validated, $entity);

            return $this->successResponse($doc, 'Document uploaded', 201);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Upload Document');
        }
    }

    /**
     * Get my documents
     *
     * Retrieves list of user's own and accessible documents.
     *
     * @OA\Get(
     *     path="/docs/my",
     *     operationId="getMyDocuments",
     *     tags={"Documents"},
     *     summary="Get my documents",
     *     description="List own and accessible documents",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Documents retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Documents retrieved"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getMyDocs(Request $request): JsonResponse
    {
        try {
            $docs = $this->docService->getMyDocuments(Auth::user());
            return $this->successResponse($docs, 'Documents retrieved');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Get My Documents');
        }
    }

    /**
     * Create temp group
     *
     * Creates a new temporary document group for quick access/sharing.
     *
     * @OA\Post(
     *     path="/docs/groups",
     *     operationId="createDocGroup",
     *     tags={"Documents"},
     *     summary="Create document group",
     *     description="Create temp group for documents",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Project Files"),
     *             @OA\Property(property="description", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Group created",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=201),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S201"),
     *             @OA\Property(property="message", type="string", example="Group created"),
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
    public function createGroup(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string',
                'description' => 'string|nullable',
            ]);

            $group = $this->docService->createGroup($validated);

            return $this->successResponse($group, 'Group created', 201);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Create Group');
        }
    }

    /**
     * Add document to group
     *
     * Adds a document to a temporary group.
     *
     * @OA\Post(
     *     path="/docs/groups/{groupId}/add",
     *     operationId="addToDocGroup",
     *     tags={"Documents"},
     *     summary="Add to group",
     *     description="Add document to temp group",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="groupId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"doc_id"},
     *             @OA\Property(property="doc_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Added to group",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Added to group")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @param int $groupId
     * @return JsonResponse
     */
    public function addToGroup(Request $request, int $groupId): JsonResponse
    {
        try {
            $validated = $request->validate(['doc_id' => 'required|integer|exists:documents,id']);

            $group = DocGroup::findOrFail($groupId);
            $doc = Document::findOrFail($validated['doc_id']);

            $this->docService->addToGroup($group, $doc);

            return $this->successResponse(null, 'Added to group');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Add to Group');
        }
    }

    /**
     * Remove document from group
     *
     * Removes a document from a temporary group.
     *
     * @OA\Delete(
     *     path="/docs/groups/{groupId}/remove/{docId}",
     *     operationId="removeFromDocGroup",
     *     tags={"Documents"},
     *     summary="Remove from group",
     *     description="Remove document from temp group",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="groupId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="docId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Removed from group",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Removed from group")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param int $groupId
     * @param int $docId
     * @return JsonResponse
     */
    public function removeFromGroup(int $groupId, int $docId): JsonResponse
    {
        try {
            $group = DocGroup::findOrFail($groupId);
            $doc = Document::findOrFail($docId);

            $this->docService->removeFromGroup($group, $doc);

            return $this->successResponse(null, 'Removed from group');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Remove from Group');
        }
    }

    /**
     * Download group as ZIP
     *
     * Downloads all documents in a group as a ZIP file.
     *
     * @OA\Get(
     *     path="/docs/groups/{groupId}/zip",
     *     operationId="downloadGroupZip",
     *     tags={"Documents"},
     *     summary="Download group ZIP",
     *     description="Download temp group as ZIP",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="groupId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="ZIP downloaded",
     *         @OA\MediaType(
     *             mediaType="application/zip",
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Group not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param int $groupId
     * @return Response
     */
    public function downloadGroupZip(int $groupId): Response
    {
        try {
            $group = DocGroup::findOrFail($groupId);
            $zipPath = $this->docService->downloadGroupZip($group);

            return response()->download($zipPath)->deleteFileAfterSend(true);
        } catch (Throwable $e) {
            return $this->handleException($e, 'Download Group ZIP');
        }
    }

    /**
     * Search documents
     *
     * Searches accessible documents by query.
     *
     * @OA\Get(
     *     path="/docs/search",
     *     operationId="searchDocuments",
     *     tags={"Documents"},
     *     summary="Search documents",
     *     description="Full-text search on accessible documents",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", example="contract")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Search results"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
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
    public function search(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['query' => 'required|string']);
            $results = $this->docService->search($validated['query'], Auth::user());
            return $this->successResponse($results, 'Search results');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Search Documents');
        }
    }

    /**
     * Get document analytics
     *
     * Retrieves analytics like views/downloads for user's documents.
     *
     * @OA\Get(
     *     path="/docs/analytics",
     *     operationId="getDocAnalytics",
     *     tags={"Documents"},
     *     summary="Get analytics",
     *     description="Document views/downloads stats",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Analytics retrieved",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Analytics retrieved"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        try {
            $analytics = $this->docService->getAnalytics(Auth::user());
            return $this->successResponse($analytics, 'Analytics retrieved');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Get Analytics');
        }
    }

    /**
     * Approve document
     *
     * Approves a document if workflow required.
     *
     * @OA\Post(
     *     path="/docs/{docId}/approve",
     *     operationId="approveDocument",
     *     tags={"Documents"},
     *     summary="Approve document",
     *     description="Approve pending document",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="docId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Document approved",
     *         @OA\JsonContent(
     *             @OA\Property(property="http_status", type="integer", example=200),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="code", type="string", example="S200"),
     *             @OA\Property(property="message", type="string", example="Document approved")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Document not found"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     *
     * @param int $docId
     * @return JsonResponse
     */
    public function approve(int $docId): JsonResponse
    {
        try {
            $doc = Document::findOrFail($docId);
            $this->docService->approve($doc, Auth::user());
            return $this->successResponse(null, 'Document approved');
        } catch (Throwable $e) {
            return $this->handleException($e, 'Approve Document');
        }
    }
}

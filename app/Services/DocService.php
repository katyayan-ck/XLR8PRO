<?php

namespace App\Services;

use App\Models\User;
use App\Models\Utilities\Docs\Document;
use App\Models\Utilities\Docs\DocGroup;
use App\Models\Utilities\Docs\DocAccess;
use App\Services\KeywordValueService;
use App\Services\EntityHistoryService;
use App\Services\NotificationService;
use App\Services\RBACService;
use App\Services\DataScopeService;
use App\Services\ApprovalService;
use App\Services\SystemSettingService;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;
use ZipArchive;

class DocService
{
    protected $historyService;
    protected $notificationService;
    protected $rbacService;
    protected $dataScopeService;
    protected $approvalService;
    protected $settingService;

    public function __construct(
        EntityHistoryService $historyService,
        NotificationService $notificationService,
        RBACService $rbacService,
        DataScopeService $dataScopeService,
        ApprovalService $approvalService,
        SystemSettingService $settingService
    ) {
        $this->historyService = $historyService;
        $this->notificationService = $notificationService;
        $this->rbacService = $rbacService;
        $this->dataScopeService = $dataScopeService;
        $this->approvalService = $approvalService;
        $this->settingService = $settingService;
    }

    public function upload(array $data, $entity = null): Document
    {
        try {
            $categoryId = KeywordValueService::getValueId('doc_categories', $data['category_key']);

            $doc = Document::create([
                'title' => $data['title'],
                'description' => $data['description'],
                'category_id' => $categoryId,
                'expiry_date' => $data['expiry_date'] ?? null,
            ]);

            if ($entity) {
                $doc->documentable_type = get_class($entity);
                $doc->documentable_id = $entity->id;
                $doc->save();
            }

            // Upload file
            if (isset($data['file'])) {
                $media = $doc->addMedia($data['file'])->toMediaCollection('documents');

                // AI Tagging if enabled and image
                if ($this->settingService->get('ai_tagging_enabled', false) && in_array($media->mime_type, ['image/jpeg', 'image/png'])) {
                    $tags = $this->getAiTags($media->getPath());
                    $doc->update(['tags' => $tags]);
                }
            }

            // Log history
            $master = $doc->commMaster ?? $this->historyService->createMaster($doc, $doc->title, $doc->description);
            $this->historyService->addThread($master, 'created', 'Document Uploaded');

            // If approval needed
            if ($data['requires_approval']) {
                $this->approvalService->initializeApproval('document', [], Auth::user());
            }

            // Notify on expiry if set
            if ($doc->expiry_date) {
                $this->scheduleExpiryNotification($doc);
            }

            return $doc;
        } catch (Throwable $e) {
            Log::error('Document upload failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function getAiTags(string $path): array
    {
        $client = new ImageAnnotatorClient();
        $image = file_get_contents($path);
        $response = $client->labelDetection($image);
        $labels = $response->getLabelAnnotations();

        $tags = [];
        if ($labels) {
            foreach ($labels as $label) {
                $tags[] = $label->getDescription();
            }
        }
        $client->close();
        return $tags;
    }

    public function hasAccess(User $user, Document $doc): bool
    {
        if ($doc->created_by === $user->id) return true;

        if (DocAccess::where('document_id', $doc->id)->where('user_id', $user->id)->exists()) return true;

        if ($this->rbacService->canUserAccess($user, 'document', 'view')) return true;

        if ($doc->documentable) {
            $scopes = $this->dataScopeService->getUserScopes($user, get_class($doc->documentable));
            // Implement combo match logic
            return true;  // Placeholder
        }

        if ($doc->documentable && method_exists($doc->documentable, 'hasAccess')) {
            return $doc->documentable->hasAccess($user);
        }

        return false;
    }

    public function createGroup(array $data): DocGroup
    {
        return DocGroup::create([
            'user_id' => Auth::id(),
            'name' => $data['name'],
            'description' => $data['description'],
        ]);
    }

    public function addToGroup(DocGroup $group, Document $doc): void
    {
        $group->documents()->attach($doc->id);
    }

    public function removeFromGroup(DocGroup $group, Document $doc): void
    {
        $group->documents()->detach($doc->id);
    }

    public function downloadGroupZip(DocGroup $group): string
    {
        $zipPath = storage_path('app/temp/' . Str::uuid() . '.zip');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        foreach ($group->documents as $doc) {
            foreach ($doc->media as $media) {
                $zip->addFile($media->getPath(), $media->file_name);
            }
        }

        $zip->close();
        return $zipPath;
    }

    public function search(string $query, User $user): Collection
    {
        return Document::search($query)->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhereHas('accesses', function ($qa) use ($user) {
                    $qa->where('user_id', $user->id);
                });
            // Add RBAC/scope
        })->get();
    }

    public function getAnalytics(User $user): array
    {
        return [
            'views' => $user->audits()->where('event', 'viewed')->count(),
            'downloads' => $user->audits()->where('event', 'downloaded')->count(),
        ];
    }

    private function scheduleExpiryNotification(Document $doc): void
    {
        // Queue job for 7 days before expiry
        // Example: dispatch(new NotifyExpiry($doc))->delay(Carbon::parse($doc->expiry_date)->subDays(7));
        // In job: $this->notificationService->sendAndLogNotification($doc->created_by_user, 'expiry', 'Document Expiring', 'Expires soon');
    }

    public function approve(Document $doc, User $approver): void
    {
        $this->approvalService->approve($doc, $approver);
        $this->historyService->addThread($doc->commMaster, 'approved', 'Approved');
    }
}

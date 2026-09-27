<?php

namespace App\Services;

use App\Models\User;
use App\Models\Utilities\Docs\DocGroup;
use App\Models\Utilities\Docs\Document;
use App\Services\Platform\Chat\ChatService;
use App\Services\Platform\Docs\DocsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Models\Audit;
use RuntimeException;

/**
 * Legacy document API kept as a thin adapter over the Docs platform service (DEC-061) for the
 * mobile v1 docs endpoints. New code uses the Docs facade. BUG-139: the old implementation wrote
 * to non-existent tables and called missing methods (getMyDocuments, ApprovalService::approve).
 */
class DocService
{
    public function __construct(private readonly DocsService $docs) {}

    /** @param  array<string, mixed>  $data */
    public function upload(array $data, ?Model $entity = null): Document
    {
        $meta = ['title' => $data['title'] ?? null, 'description' => $data['description'] ?? null, 'expiry_date' => $data['expiry_date'] ?? null];
        $result = isset($data['file'])
            ? $this->docs->attach($entity, $data['file'], $data['collection'] ?? 'docs', array_filter($meta))
            : $this->docs->card($meta + ['info_body' => $data['info_body'] ?? $data['description'] ?? ''], $entity);

        if (! $result->ok) {
            throw new RuntimeException($result->message);
        }

        return Document::query()->findOrFail($result->get('id'));
    }

    public function hasAccess(User $user, Document $doc): bool
    {
        return $this->docs->canView($doc->id, $user->id);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function getMyDocuments(User $user): Collection
    {
        return collect($this->docs->mine($user->id));
    }

    /** @param  array<string, mixed>  $data */
    public function createGroup(array $data): DocGroup
    {
        return $this->docs->createGroup((int) (backpack_auth()->id() ?? auth()->id()), (string) ($data['name'] ?? ''), $data['description'] ?? null);
    }

    public function addToGroup(DocGroup $group, Document $doc): void
    {
        $this->docs->addToGroup((int) $group->user_id, $group->id, $doc->id);
    }

    public function removeFromGroup(DocGroup $group, Document $doc): void
    {
        $this->docs->removeFromGroup((int) $group->user_id, $group->id, $doc->id);
    }

    public function downloadGroupZip(DocGroup $group): string
    {
        $result = $this->docs->zip((int) $group->user_id, $group->id);
        if (! $result->ok) {
            throw new RuntimeException($result->message);
        }

        return (string) $result->get('path');
    }

    /** @return Collection<int, array<string, mixed>> */
    public function search(string $query, User $user): Collection
    {
        return collect($this->docs->library(['q' => $query], $user->id)['items'])
            ->merge(collect($this->docs->mine($user->id))->filter(fn ($d) => str_contains(strtolower($d['name']), strtolower($query))))
            ->unique('id')->values();
    }

    /** @return array{views: int, downloads: int} */
    public function getAnalytics(User $user): array
    {
        $audits = fn (string $event) => Audit::query()->where('user_id', $user->id)->where('auditable_type', Document::class)->where('event', $event)->count();

        return ['views' => $audits('viewed'), 'downloads' => $audits('downloaded')];
    }

    /** Records the approval on the document's timeline (formal approvals use the Approval service). */
    public function approve(Document $doc, User $approver): void
    {
        app(ChatService::class)->event($doc, 'APPROVED', 'Approved by '.$approver->display_name, [], $approver->id);
    }
}

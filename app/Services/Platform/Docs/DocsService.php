<?php

declare(strict_types=1);

namespace App\Services\Platform\Docs;

use App\Models\Admin\UserScope;
use App\Models\User;
use App\Models\Utilities\Docs\DocAccess;
use App\Models\Utilities\Docs\DocGroup;
use App\Models\Utilities\Docs\Document;
use App\Services\Platform\Chat\ChatService;
use App\Services\Platform\Settings\SettingsService;
use App\Support\Result;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Document utility (FRS §4): store, classify, entitle, group and attach files and information
 * cards. The only writer of the docs tables; `canView()` is the only visibility check (DOC-04).
 *
 * Entitlements (`docs_access.access_type`): USER (user_id), DESIGNATION, DEPARTMENT, SCOPE
 * (combo {type, code}) and PARENT (anyone who may see the record it is attached to). A document
 * without entitlements is visible to its owner and, when attached, to whoever may see the record;
 * an unattached library document without entitlements is visible to holders of UTL_DOCS_VIEW.
 */
final class DocsService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Store a file (FRS DOC-01), attached to `$model` when given, filed in the library when the
     * meta carries a path. Records a Chat ATTACHED event on the record (DOC-10).
     *
     * @param  array{title?: string, description?: string, category_id?: int, expiry_date?: string, path_entity?: string, path_location?: string, path_category?: string, path_sub?: string, path_item?: string, fy?: string, caption?: string}  $meta
     */
    public function attach(?Model $model, UploadedFile $file, string $collection = 'docs', array $meta = [], ?int $actorId = null, bool $withEvent = true): Result
    {
        $actorId ??= $this->actor();
        $error = $this->fileError($file);
        if ($error !== null) {
            return Result::fail('INVALID_FILE', $error);
        }

        $doc = DB::transaction(function () use ($model, $file, $collection, $meta, $actorId) {
            $doc = Document::create($this->documentAttributes($model, $meta + ['title' => $file->getClientOriginalName()], $actorId) + [
                'kind' => str_starts_with((string) $file->getMimeType(), 'image/') ? 'IMAGE' : 'DOCUMENT',
                'collection' => $collection,
            ]);
            $doc->addMedia($file)->usingName((string) ($meta['title'] ?? $file->getClientOriginalName()))->toMediaCollection($collection);

            return $doc;
        });

        if ($model && $withEvent) {
            app(ChatService::class)->event($model, 'ATTACHED', 'File attached: '.$doc->title, ['doc_id' => $doc->id], $actorId);
        }

        return Result::ok($this->dto($doc->fresh('media')));
    }

    /**
     * Information card with no file (FRS DOC-02).
     *
     * @param  array<string, mixed>  $meta  title (required), info_body, path_*, fy…
     */
    public function card(array $meta, ?Model $model = null, ?int $actorId = null): Result
    {
        if (trim((string) ($meta['title'] ?? '')) === '') {
            return Result::fail('INVALID', 'An information card needs a title.');
        }
        $doc = Document::create($this->documentAttributes($model, $meta, $actorId ?? $this->actor()) + [
            'kind' => 'INFORMATION', 'collection' => 'docs', 'info_body' => preg_replace('/<(\/?)(p|br|ul|ol|li|strong|em)\b[^>]*>/i', '<$1$2>', strip_tags((string) ($meta['info_body'] ?? ''), '<p><br><ul><ol><li><strong><em>')),
        ]);

        return Result::ok($this->dto($doc));
    }

    /**
     * Replace who may see a document (FRS DOC-03).
     *
     * @param  array{users?: list<int>, designations?: list<string>, departments?: list<string>, scopes?: array<string, list<string>>, parent?: bool}  $spec
     */
    public function entitle(int $docId, array $spec): Result
    {
        $doc = Document::query()->find($docId);
        if (! $doc) {
            return Result::fail('NOT_FOUND', 'Document not found.');
        }

        DB::transaction(function () use ($doc, $spec) {
            DocAccess::query()->where('document_id', $doc->id)->forceDelete();
            $rows = [];
            foreach ($spec['users'] ?? [] as $userId) {
                $rows[] = ['access_type' => 'USER', 'user_id' => (int) $userId, 'access_combo' => null];
            }
            foreach ($spec['designations'] ?? [] as $code) {
                $rows[] = ['access_type' => 'DESIGNATION', 'user_id' => null, 'access_combo' => ['code' => strtoupper($code)]];
            }
            foreach ($spec['departments'] ?? [] as $code) {
                $rows[] = ['access_type' => 'DEPARTMENT', 'user_id' => null, 'access_combo' => ['code' => strtoupper($code)]];
            }
            foreach ($spec['scopes'] ?? [] as $type => $codes) {
                foreach ((array) $codes as $code) {
                    $rows[] = ['access_type' => 'SCOPE', 'user_id' => null, 'access_combo' => ['type' => $type, 'code' => strtoupper($code)]];
                }
            }
            if (! empty($spec['parent'])) {
                $rows[] = ['access_type' => 'PARENT', 'user_id' => null, 'access_combo' => null];
            }
            foreach ($rows as $row) {
                DocAccess::create($row + ['document_id' => $doc->id]);
            }
        });

        return Result::ok(['entitlements' => $doc->accesses()->count()]);
    }

    /** The only visibility check for documents (FRS DOC-04). */
    public function canView(int $docId, int $userId): bool
    {
        $doc = Document::query()->with('accesses')->find($docId);
        $user = User::query()->with('employee')->find($userId);
        if (! $doc || ! $user) {
            return false;
        }
        if ($user->isSuperAdmin() || $user->can('UTL_DOCS_MANAGE') || (int) $doc->owner_id === $userId || (int) $doc->created_by === $userId) {
            return true;
        }
        if ($doc->accesses->isEmpty()) {
            // Attached files follow their record; unattached library files follow UTL_DOCS_VIEW.
            return $doc->documentable_type !== null ? $this->parentAllows($doc, $userId) : $user->can('UTL_DOCS_VIEW');
        }

        $employee = $user->employee;
        foreach ($doc->accesses as $access) {
            $combo = (array) $access->access_combo;
            $allowed = match ($access->access_type) {
                'USER' => (int) $access->user_id === $userId,
                'DESIGNATION' => $employee && strtoupper((string) $employee->designation_code) === ($combo['code'] ?? null),
                'DEPARTMENT' => $employee && strtoupper((string) $employee->primary_dept_code) === ($combo['code'] ?? null),
                'SCOPE' => UserScope::query()->where('user_id', $userId)->where('scope_type', $combo['type'] ?? '')->where('scope_code', $combo['code'] ?? '')->where('is_active', true)->exists(),
                'PARENT' => $this->parentAllows($doc, $userId),
                default => false,
            };
            if ($allowed) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array<string, mixed>> documents attached to a record (FRS DOC-05) */
    public function listFor(Model $model, ?string $collection = null, ?int $viewerId = null): array
    {
        $viewerId ??= $this->actor();

        return Document::query()->where('documentable_type', $model::class)->where('documentable_id', $model->getKey())
            ->when($collection, fn (Builder $q) => $q->where('collection', $collection))
            ->with('media')->latest('id')->get()
            ->filter(fn (Document $d) => $viewerId === null || $this->canView($d->id, $viewerId))
            ->map(fn (Document $d) => $this->dto($d))->values()->all();
    }

    /**
     * Library browse (FRS DOC-05/08): path levels + FY + kind + search, only what the viewer may see.
     *
     * @param  array{path_entity?: string, path_location?: string, path_category?: string, path_sub?: string, path_item?: string, fy?: string, kind?: string, q?: string}  $filters
     * @return array{items: list<array<string, mixed>>, facets: array<string, list<string>>}
     */
    public function library(array $filters, int $viewerId): array
    {
        $query = Document::query()->whereNotNull('path_entity')->with('media');
        foreach (['path_entity', 'path_location', 'path_category', 'path_sub', 'path_item', 'fy', 'kind'] as $field) {
            if (($filters[$field] ?? '') !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        if (($filters['q'] ?? '') !== '') {
            $query->where(fn (Builder $q) => $q->where('title', 'like', '%'.$filters['q'].'%')->orWhere('description', 'like', '%'.$filters['q'].'%'));
        }

        $items = $query->latest('id')->limit(500)->get()->filter(fn (Document $d) => $this->canView($d->id, $viewerId))->map(fn (Document $d) => $this->dto($d))->values()->all();

        $facets = [];
        foreach (['path_entity', 'path_location', 'path_category', 'path_sub', 'path_item', 'fy'] as $field) {
            $facets[$field] = Document::query()->whereNotNull($field)->distinct()->orderBy($field)->pluck($field)->all();
        }

        return ['items' => $items, 'facets' => $facets];
    }

    // ── Cart and groups (FRS DOC-06/07) ────────────────────────────────────

    public function cartAdd(int $userId, int $docId): Result
    {
        if (! $this->canView($docId, $userId)) {
            return Result::fail('FORBIDDEN', 'You may not see this document.');
        }
        $this->cartGroup($userId)->documents()->syncWithoutDetaching([$docId]);

        return Result::ok(['count' => $this->cartGroup($userId)->documents()->count()]);
    }

    public function cartRemove(int $userId, int $docId): Result
    {
        $this->cartGroup($userId)->documents()->detach($docId);

        return Result::ok();
    }

    /** @return list<array<string, mixed>> */
    public function cart(int $userId): array
    {
        return $this->cartGroup($userId)->documents()->with('media')->get()->map(fn (Document $d) => $this->dto($d))->all();
    }

    /** Save the cart as a named pack and empty the cart. */
    public function cartSaveAs(int $userId, string $name, ?string $purpose = null): Result
    {
        $cart = $this->cartGroup($userId);
        $ids = $cart->documents()->pluck('xlr8_utils_docs_document.id')->all();
        if ($ids === []) {
            return Result::fail('EMPTY', 'The cart is empty.');
        }
        $group = DocGroup::create(['user_id' => $userId, 'name' => trim($name) ?: 'Pack '.now()->format('d-m-Y H:i'), 'description' => $purpose]);
        $group->documents()->sync($ids);
        $cart->documents()->detach();

        return Result::ok(['group_id' => $group->id, 'count' => count($ids)]);
    }

    /** @return list<array{id: int, name: string, description: ?string, count: int}> */
    public function groups(int $userId): array
    {
        return DocGroup::query()->where('user_id', $userId)->where('name', '!=', config('platform.docs.cart_group'))->withCount('documents')->orderBy('name')->get()
            ->map(fn (DocGroup $g) => ['id' => $g->id, 'name' => $g->name, 'description' => $g->description, 'count' => $g->documents_count])->all();
    }

    public function createGroup(int $userId, string $name, ?string $description = null): DocGroup
    {
        return DocGroup::create(['user_id' => $userId, 'name' => trim($name) ?: 'Pack', 'description' => $description]);
    }

    public function addToGroup(int $userId, int $groupId, int $docId): Result
    {
        $group = DocGroup::query()->where('user_id', $userId)->find($groupId);
        if (! $group || ! $this->canView($docId, $userId)) {
            return Result::fail('FORBIDDEN', 'Group not found or document not visible.');
        }
        $group->documents()->syncWithoutDetaching([$docId]);

        return Result::ok();
    }

    public function removeFromGroup(int $userId, int $groupId, int $docId): Result
    {
        $group = DocGroup::query()->where('user_id', $userId)->find($groupId);
        if (! $group) {
            return Result::fail('NOT_FOUND', 'Group not found.');
        }
        $group->documents()->detach($docId);

        return Result::ok();
    }

    /** @return list<array<string, mixed>> documents the user owns */
    public function mine(int $userId): array
    {
        return Document::query()->where('owner_id', $userId)->orWhere('created_by', $userId)->with('media')->latest('id')->limit(200)->get()
            ->map(fn (Document $d) => $this->dto($d))->all();
    }

    public function renameGroup(int $userId, int $groupId, string $name): Result
    {
        $group = DocGroup::query()->where('user_id', $userId)->find($groupId);
        if (! $group) {
            return Result::fail('NOT_FOUND', 'Group not found.');
        }
        $group->update(['name' => trim($name)]);

        return Result::ok();
    }

    public function deleteGroup(int $userId, int $groupId): Result
    {
        $group = DocGroup::query()->where('user_id', $userId)->find($groupId);
        if (! $group) {
            return Result::fail('NOT_FOUND', 'Group not found.');
        }
        $group->documents()->detach();
        $group->delete();

        return Result::ok();
    }

    /** Zip of a group, containing only files the user may see. Returns the zip path. */
    public function zip(int $userId, int $groupId): Result
    {
        $group = DocGroup::query()->where('user_id', $userId)->find($groupId);
        if (! $group) {
            return Result::fail('NOT_FOUND', 'Group not found.');
        }
        $dir = storage_path('app/temp');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir.DIRECTORY_SEPARATOR.Str::slug($group->name ?: 'pack').'-'.Str::random(6).'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $added = 0;
        foreach ($group->documents()->with('media')->get() as $doc) {
            if (! $this->canView($doc->id, $userId)) {
                continue;
            }
            foreach ($doc->media as $media) {
                if (is_file($media->getPath())) {
                    $zip->addFile($media->getPath(), $doc->id.'-'.$media->file_name);
                    $added++;
                }
            }
        }
        $zip->close();

        return $added > 0 ? Result::ok(['path' => $path, 'files' => $added]) : Result::fail('EMPTY', 'No files you may download are in this pack.');
    }

    /** Soft delete; entitlements stay for audit, the purge job removes files later (DOC-09). */
    /**
     * The newest live document of one collection on a record (e.g. a booking's current policy copy), or null.
     */
    public function latestFor(Model $model, string $collection): ?Document
    {
        return Document::query()->where('documentable_type', $model::class)->where('documentable_id', $model->getKey())
            ->where('collection', $collection)->with('media')->latest('id')->first();
    }

    /**
     * Soft-delete every live document of one collection on a record, before a replacement is attached (DEC-069).
     * The record's own workflow has already authorised the caller, so no owner check here; the purge job removes
     * the files later, like any other deleted document.
     */
    public function supersede(Model $model, string $collection, ?int $actorId = null, ?int $exceptId = null): int
    {
        $docs = Document::query()->where('documentable_type', $model::class)->where('documentable_id', $model->getKey())
            ->where('collection', $collection)->when($exceptId, fn (Builder $q) => $q->whereKeyNot($exceptId))->get();
        foreach ($docs as $doc) {
            $doc->forceFill(['deleted_by' => $actorId ?? $this->actor()])->save();
            $doc->delete();
        }

        return $docs->count();
    }

    public function delete(int $docId, ?int $actorId = null): Result
    {
        $actorId ??= $this->actor();
        $doc = Document::query()->find($docId);
        if (! $doc) {
            return Result::fail('NOT_FOUND', 'Document not found.');
        }
        $actor = $actorId ? User::query()->find($actorId) : null;
        if ((int) $doc->owner_id !== (int) $actorId && ! $actor?->can('UTL_DOCS_MANAGE')) {
            return Result::fail('FORBIDDEN', 'Only the owner may delete this document.');
        }
        $doc->delete();

        return Result::ok();
    }

    /** Permanently remove documents soft-deleted more than `$days` ago (with their files). */
    public function purge(int $days = 30): int
    {
        $count = 0;
        Document::onlyTrashed()->where('deleted_at', '<', now()->subDays($days))->each(function (Document $doc) use (&$count) {
            $doc->accesses()->forceDelete();
            $doc->groups()->detach();
            $doc->clearMediaCollection();
            foreach (config('platform.docs.collections', []) as $collection) {
                $doc->clearMediaCollection($collection);
            }
            $doc->forceDelete();
            $count++;
        });

        return $count;
    }

    /** @return array<string, mixed> the document DTO every caller receives */
    public function dto(Document $doc): array
    {
        $media = $doc->relationLoaded('media') ? $doc->media->first() : $doc->media()->first();

        return [
            'id' => $doc->id,
            'name' => $doc->title,
            'kind' => $doc->kind,
            'collection' => $doc->collection,
            'mime' => $media?->mime_type,
            'size' => $media?->size,
            'url' => $media ? rescue(fn () => $media->getUrl(), null, false) : null,
            'is_image' => $doc->kind === 'IMAGE',
            'info' => $doc->kind === 'INFORMATION' ? $doc->info_body : null,
            'path' => $doc->libraryPath(),
            'fy' => $doc->fy,
            'owner_id' => $doc->owner_id,
            'created_at' => $doc->created_at?->toIso8601String(),
        ];
    }

    private function cartGroup(int $userId): DocGroup
    {
        return DocGroup::query()->firstOrCreate(['user_id' => $userId, 'name' => config('platform.docs.cart_group')], ['description' => 'Cart']);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function documentAttributes(?Model $model, array $meta, ?int $actorId): array
    {
        return [
            'documentable_type' => $model ? $model::class : null,
            'documentable_id' => $model?->getKey(),
            'title' => mb_substr(strip_tags((string) ($meta['title'] ?? 'Document')), 0, 250),
            'description' => isset($meta['description']) ? strip_tags((string) $meta['description']) : ($meta['caption'] ?? null),
            'category_id' => $meta['category_id'] ?? null,
            'expiry_date' => $meta['expiry_date'] ?? null,
            'path_entity' => $meta['path_entity'] ?? null,
            'path_location' => $meta['path_location'] ?? null,
            'path_category' => $meta['path_category'] ?? null,
            'path_sub' => $meta['path_sub'] ?? null,
            'path_item' => $meta['path_item'] ?? null,
            'fy' => $meta['fy'] ?? null,
            'owner_id' => $actorId,
        ];
    }

    private function fileError(UploadedFile $file): ?string
    {
        $maxKb = (int) $this->settings->get('docs.max_upload_kb', 10240);
        $allowed = array_filter(array_map('trim', explode(',', strtolower((string) $this->settings->get('docs.allowed_mimes')))));
        if (! $file->isValid()) {
            return 'The upload failed.';
        }
        if ($file->getSize() > $maxKb * 1024) {
            return "The file is larger than {$maxKb} KB.";
        }
        $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension());
        if ($allowed !== [] && ! in_array($extension, $allowed, true)) {
            return "Files of type .{$extension} are not allowed.";
        }

        return null;
    }

    private function parentAllows(Document $doc, int $userId): bool
    {
        $parent = $doc->documentable;
        if ($parent === null) {
            return false;
        }

        return app(ChatService::class)->canView($parent, $userId);
    }

    private function actor(): ?int
    {
        return auth(backpack_guard_name())->id() ?? auth()->id();
    }

    /** @return Collection<int, Document> */
    public function recent(int $viewerId, int $limit = 20): Collection
    {
        return Document::query()->latest('id')->limit($limit * 3)->get()->filter(fn (Document $d) => $this->canView($d->id, $viewerId))->take($limit)->values();
    }
}

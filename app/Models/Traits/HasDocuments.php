<?php

namespace App\Models\Traits;

use App\Models\Utilities\Docs\Document;
use App\Services\Platform\Docs\DocsService;
use App\Support\Result;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Opt a model into the Document utility (FRS §4): `$model->attachDocument($file)`,
 * `$model->documentsList()`. Files always go through DocsService (entitlements, Chat event).
 *
 * One-file slots on a record (a receipt's proof, a booking's policy copy — DEC-069):
 * `replaceDocument('policy_copy', $file)`, `documentFor('policy_copy')`, `documentUrl('policy_copy')`,
 * `hasDocumentIn('policy_copy')`, `removeDocuments('policy_copy')`. Links go through the access-checked
 * download route (`?inline=1` to preview), never a public storage URL.
 */
trait HasDocuments
{
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** @param  array<string, mixed>  $meta */
    public function attachDocument(UploadedFile $file, string $collection = 'docs', array $meta = []): Result
    {
        return app(DocsService::class)->attach($this, $file, $collection, $meta);
    }

    /** @return list<array<string, mixed>> */
    public function documentsList(?string $collection = null): array
    {
        return app(DocsService::class)->listFor($this, $collection);
    }

    /**
     * The current file of a one-file slot, as `{id, name, mime, size, is_image, view_url, download_url}`, or null.
     *
     * @return array{id: int, name: string, mime: ?string, size: ?int, is_image: bool, view_url: string, download_url: string}|null
     */
    public function documentFor(string $collection): ?array
    {
        $doc = app(DocsService::class)->latestFor($this, $collection);
        if (! $doc) {
            return null;
        }
        $media = $doc->media->first();

        return [
            'id' => $doc->id,
            'name' => (string) ($media ? $media->file_name : $doc->title),
            'mime' => $media?->mime_type,
            'size' => $media?->size,
            'is_image' => $doc->kind === 'IMAGE',
            'view_url' => route('utils.docs.download', ['id' => $doc->id, 'inline' => 1]),
            'download_url' => route('utils.docs.download', ['id' => $doc->id]),
        ];
    }

    /** Preview link of a one-file slot ('' when empty) — use where views used getFirstMediaUrl(). */
    public function documentUrl(string $collection, bool $inline = true): string
    {
        $doc = $this->documentFor($collection);

        return $doc === null ? '' : ($inline ? $doc['view_url'] : $doc['download_url']);
    }

    public function hasDocumentIn(string $collection): bool
    {
        return app(DocsService::class)->latestFor($this, $collection) !== null;
    }

    /**
     * Put a new file into a one-file slot: the previous one is superseded (soft-deleted), the new one attached without
     * a timeline event (the caller's workflow records its own). Upload rules come from Settings (`docs.*`).
     *
     * @param  array<string, mixed>  $meta
     *
     * @throws ValidationException when the file is too large or of a type Settings does not allow
     */
    public function replaceDocument(string $collection, UploadedFile $file, array $meta = [], ?string $errorField = null): Document
    {
        $docs = app(DocsService::class);
        $result = $docs->attach($this, $file, $collection, $meta + ['title' => $file->getClientOriginalName()], null, withEvent: false);
        if (! $result->ok) {
            throw ValidationException::withMessages([$errorField ?? $collection => $result->message]);
        }
        $docs->supersede($this, $collection, null, (int) $result->get('id'));

        return Document::query()->findOrFail($result->get('id'));
    }

    /** Empty a slot (soft delete); returns how many documents were removed. */
    public function removeDocuments(string $collection): int
    {
        return app(DocsService::class)->supersede($this, $collection);
    }
}

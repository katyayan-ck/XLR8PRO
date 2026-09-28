<?php

namespace App\Models\Traits;

use App\Models\Utilities\Docs\Document;
use App\Services\Platform\Docs\DocsService;
use App\Support\Result;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;

/**
 * Opt a model into the Document utility (FRS §4): `$model->attachDocument($file)`,
 * `$model->documentsList()`. Files always go through DocsService (entitlements, Chat event).
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
}

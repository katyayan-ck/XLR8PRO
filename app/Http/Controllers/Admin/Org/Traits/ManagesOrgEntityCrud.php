<?php

namespace App\Http\Controllers\Admin\Org\Traits;

use Illuminate\Http\Request;

/**
 * Shared helpers for the "code is the real primary key" org reference-data
 * CRUD controllers (Designation, Department, Division, Vertical, Branch,
 * Location, ...). See App\Services\Org\OrgEntityGuard for the dependency
 * check these controllers should run before allowing is_active -> false.
 */
trait ManagesOrgEntityCrud
{
    /** Code must never change after creation — always drop it from an update payload. */
    protected function stripImmutableCode(array $validated): array
    {
        unset($validated['code']);

        return $validated;
    }

    /** @param  string  $collection  The entity's own single-file image collection, e.g. 'branch_image'. */
    protected function handleImageUpload(Request $request, $model, string $collection): void
    {
        if ($request->hasFile($collection)) {
            $model->addMediaFromRequest($collection)->toMediaCollection($collection);
        }
    }

    /**
     * Adds any newly-uploaded files to the shared 'documents' collection
     * (inherited from BaseModel) and removes any the user explicitly
     * checked for removal.
     */
    protected function handleDocumentUploads(Request $request, $model): void
    {
        if ($request->hasFile('documents')) {
            foreach ((array) $request->file('documents') as $file) {
                $model->addMedia($file)->toMediaCollection('documents');
            }
        }

        foreach ((array) $request->input('remove_documents', []) as $mediaId) {
            $model->media()->where('id', $mediaId)->where('collection_name', 'documents')->first()?->delete();
        }
    }

    /**
     * Removes the single-file image collection's current file when the user
     * checked "remove image" — call before handleImageUpload() so a
     * simultaneous remove+re-upload in one request still ends with the new file.
     */
    protected function handleImageRemoval(Request $request, $model, string $collection): void
    {
        if ($request->boolean('remove_image')) {
            $model->clearMediaCollection($collection);
        }
    }
}

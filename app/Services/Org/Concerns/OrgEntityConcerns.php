<?php

declare(strict_types=1);

namespace App\Services\Org\Concerns;

use App\Services\Org\OrgEntityGuard;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;

/**
 * Shared pieces of the Org master entity services (DEC-052): the image/documents upload fields,
 * the media sync that used to be copied into six services, and the dependency-checked disable.
 */
trait OrgEntityConcerns
{
    /**
     * Virtual (non-column) upload fields every Org master form offers.
     *
     * @return list<Field>
     */
    protected function mediaFields(string $imageCollection): array
    {
        return [
            Field::image($imageCollection)->label('Image'),
            Field::documents()->label('Documents'),
            Field::flag('remove_image', false)->virtual(),
            Field::make('remove_documents')->rules('array')->virtual(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function syncMedia(HasMedia $model, array $input, string $imageCollection): void
    {
        if (filter_var($input['remove_image'] ?? false, FILTER_VALIDATE_BOOL)) {
            $model->clearMediaCollection($imageCollection);
        }

        if (($input[$imageCollection] ?? null) instanceof UploadedFile) {
            $model->addMedia($input[$imageCollection])->toMediaCollection($imageCollection);
        }

        foreach ((array) ($input['documents'] ?? []) as $file) {
            if ($file instanceof UploadedFile) {
                $model->addMedia($file)->toMediaCollection('documents');
            }
        }

        foreach ((array) ($input['remove_documents'] ?? []) as $mediaId) {
            $model->media()->where('id', $mediaId)->where('collection_name', 'documents')->first()?->delete();
        }
    }

    /**
     * Refuse to disable a record that active dependents still use.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<int, string>>  $dependents  OrgEntityGuard::blockersForDisabling() format
     */
    protected function guardDisabling(Model $model, array $data, array $dependents, string $entity): void
    {
        if (! array_key_exists('is_active', $data)) {
            return;
        }

        $blockers = OrgEntityGuard::blockersForDisabling((bool) $model->is_active, (bool) $data['is_active'], $model->code, $dependents);
        if ($blockers) {
            $this->fail('is_active', "Cannot disable this {$entity} — it still has ".implode(' and ', $blockers).'. Disable those first.');
        }
    }
}

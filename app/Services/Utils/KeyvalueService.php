<?php

declare(strict_types=1);

namespace App\Services\Utils;

use App\Models\Utilities\KeyValue\Keyvalue;
use App\Services\KeywordValueService;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * Keyword values (xlr8_utils_keyvalue) — their only write path (DEC-050/055): the Key Value
 * screen, the vehicle import and the enquiry import. Reads stay on the cached KeywordValueService.
 *
 * - `keyword_code` must be an existing keyword; `code` is unique within its keyword and, like the
 *   keyword, fixed once created (other tables store it). A blank code is taken from `key`.
 * - `parent_id` holds one or more parent value ids, comma-separated (a value can sit under several
 *   parents, e.g. a sub-source under two sources); addParent() appends one.
 *
 * @extends EntityService<Keyvalue>
 */
final class KeyvalueService extends EntityService
{
    protected function model(): string
    {
        return Keyvalue::class;
    }

    protected function naturalKey(): array
    {
        return ['keyword_code', 'code'];
    }

    public function fields(): array
    {
        return [
            Field::reference('keyword_code', 'xlr8_utils_keyword_master', 50)->label('Keyword')->required()->immutable(),
            Field::code('code', 150)->label('Code')->required()->unique(['keyword_code'])->immutable(),
            Field::make('key')->format('Upper-case, max 255')->transform('trim', 'uppercase')->rules('string', 'max:255'),
            Field::make('value')->label('Value')->format('Text; repeated spaces collapsed')->transform('trim_spaces')->rules('string')->required(),
            Field::text('details', 5000)->transform('trim_spaces'),
            Field::make('parent_id')->label('Parent')->format('Parent value id(s), comma-separated')
                ->transform(fn (string $v) => implode(',', array_filter(array_map('trim', explode(',', $v)), fn ($id) => $id !== '')))
                ->rules('regex:/^\d+(,\d+)*$/'),
            Field::integer('level')->default(0),
            Field::text('path', 65535)->transform('trim_spaces'),
            Field::json('extra_data'),
            Field::make('status')->format('1 = on, 0 = off')->rules('integer', 'in:0,1')->required()->default(1),
            Field::flag('is_active', true),
        ];
    }

    /** Append a parent id to the value's parent list (no-op when already there). */
    public function addParent(Keyvalue $value, int|string $parentId): Keyvalue
    {
        $ids = array_values(array_filter(array_map('trim', explode(',', (string) $value->parent_id)), fn ($id) => $id !== ''));
        if (in_array((string) $parentId, $ids, true)) {
            return $value;
        }
        $ids[] = (string) $parentId;

        return $this->update($value, ['parent_id' => implode(',', $ids)]);
    }

    protected function derive(array $data, array $input): array
    {
        if (($data['code'] ?? null) === null && ($data['key'] ?? null) !== null) {
            $data['code'] = $this->normaliseField('code', $data['key']);
        }

        return $data;
    }

    /** @param  Keyvalue  $model */
    protected function afterSave(Model $model, array $input, bool $created): void
    {
        KeywordValueService::clearCache($model->keyword_code);
    }
}

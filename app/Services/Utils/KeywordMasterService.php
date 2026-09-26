<?php

declare(strict_types=1);

namespace App\Services\Utils;

use App\Models\Utilities\KeyValue\KeywordMaster;
use App\Services\KeywordValueService;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * Keyword masters (xlr8_utils_keyword_master) — their only write path (DEC-050/055).
 * Reads stay on the cached KeywordValueService.
 *
 * @extends EntityService<KeywordMaster>
 */
final class KeywordMasterService extends EntityService
{
    protected function model(): string
    {
        return KeywordMaster::class;
    }

    public function fields(): array
    {
        return [
            Field::code('code', 50)->label('Keyword Code')->rules('min:3')->required()->unique()->immutable(),
            Field::name('keyword')->label('Keyword')->required()->unique(),
            Field::text('description', 1000)->transform('trim_spaces'),
            Field::text('details', 5000)->transform('trim_spaces'),
            Field::json('extra_data'),
            Field::make('status')->format('1 = on, 0 = off')->rules('integer', 'in:0,1')->required()->default(1),
            Field::flag('is_recursive', false)->label('Recursive'),
            Field::flag('is_active', true),
        ];
    }

    /** @param  KeywordMaster  $model */
    protected function afterSave(Model $model, array $input, bool $created): void
    {
        KeywordValueService::clearCache($model->code);
    }
}

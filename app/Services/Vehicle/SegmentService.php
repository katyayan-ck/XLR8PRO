<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * Vehicle segment — the only write path (DEC-050).
 *
 * @extends EntityService<Segment>
 */
final class SegmentService extends EntityService
{
    protected function model(): string
    {
        return Segment::class;
    }

    public function fields(): array
    {
        return [
            // max 5: variants store segment_code in varchar(5).
            Field::code('code', 5)->label(__('vehicle.fields.code'))->required()->unique()->immutable(),
            Field::name('name')->label(__('vehicle.fields.name'))->required(),
            Field::flag('is_active')->label(__('vehicle.fields.is_active')),
        ];
    }

    protected function beforeUpdate(Model $model, array &$data): void
    {
        if ($model->is_active && array_key_exists('is_active', $data) && ! $data['is_active']) {
            $active = SubSegment::where('segment_code', $model->code)->where('is_active', 1)->count();
            if ($active > 0) {
                $this->fail('is_active', "Cannot deactivate the segment: {$active} active sub segment(s) use it.");
            }
        }
    }
}

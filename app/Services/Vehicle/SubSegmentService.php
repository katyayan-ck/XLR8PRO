<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\VehicleModel;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * Vehicle sub segment — the only write path (DEC-050).
 *
 * @extends EntityService<SubSegment>
 */
final class SubSegmentService extends EntityService
{
    protected function model(): string
    {
        return SubSegment::class;
    }

    public function fields(): array
    {
        return [
            Field::reference('segment_code', 'xlr8_vehicle_segment', 5)->label(__('vehicle.fields.segment_code'))->required(),
            Field::code('code', 20)->label(__('vehicle.fields.code'))->required()->unique()->immutable(),
            Field::name('name')->label(__('vehicle.fields.name'))->required(),
            Field::flag('is_active')->label(__('vehicle.fields.is_active')),
        ];
    }

    protected function beforeUpdate(Model $model, array &$data): void
    {
        $models = VehicleModel::where('sub_segment_code', $model->code);

        if (isset($data['segment_code']) && $data['segment_code'] !== $model->segment_code && (clone $models)->exists()) {
            $this->fail('segment_code', 'Cannot move this sub segment to another segment while vehicle models use it.');
        }
        if ($model->is_active && array_key_exists('is_active', $data) && ! $data['is_active']) {
            $active = (clone $models)->where('is_active', 1)->count();
            if ($active > 0) {
                $this->fail('is_active', "Cannot deactivate the sub segment: {$active} active model(s) use it.");
            }
        }
    }
}

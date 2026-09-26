<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;

/**
 * Vehicle model — the only write path (DEC-050). Code = OEM model stem in the hyphenated
 * format (THAR-ROXX, DEC-049), immutable once created.
 *
 * @extends EntityService<VehicleModel>
 */
final class VehicleModelService extends EntityService
{
    protected function model(): string
    {
        return VehicleModel::class;
    }

    public function fields(): array
    {
        return [
            Field::reference('segment_code', 'xlr8_vehicle_segment', 5)->label(__('vehicle.fields.segment_code'))->required(),
            Field::reference('sub_segment_code', 'xlr8_vehicle_subsegment', 20)->label(__('vehicle.fields.sub_segment_code')),
            // max 30: variants store model_code in varchar(30).
            Field::code('code', 30)->label(__('vehicle.fields.code'))->required()->unique()->immutable(),
            Field::name('name')->label(__('vehicle.fields.name'))->required(),
            Field::name('oem_name')->label(__('vehicle.fields.oem_name'))->unique(),
            Field::flag('is_active')->label(__('vehicle.fields.is_active')),
        ];
    }

    protected function beforeCreate(array &$data): void
    {
        $this->assertSubSegmentBelongsToSegment($data);
    }

    protected function beforeUpdate(Model $model, array &$data): void
    {
        $this->assertSubSegmentBelongsToSegment(array_merge($model->only(['segment_code', 'sub_segment_code']), $data));

        if ($model->is_active && array_key_exists('is_active', $data) && ! $data['is_active']) {
            $active = Variant::where('model_code', $model->code)->where('is_active', 1)->count();
            if ($active > 0) {
                $this->fail('is_active', "Cannot deactivate the model: {$active} active variant row(s) use it.");
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function assertSubSegmentBelongsToSegment(array $data): void
    {
        if (empty($data['sub_segment_code'])) {
            return;
        }
        $segment = SubSegment::where('code', $data['sub_segment_code'])->value('segment_code');
        if ($segment !== null && $segment !== ($data['segment_code'] ?? null)) {
            $this->fail('sub_segment_code', "Sub segment {$data['sub_segment_code']} belongs to segment {$segment}.");
        }
    }
}

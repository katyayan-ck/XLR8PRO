<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Vehicle variant — the only write path (DEC-050). One row per colour: `code` is the full OEM
 * code, unique per `color_code`; segment / sub segment must agree with the variant's model.
 *
 * @extends EntityService<Variant>
 */
final class VariantService extends EntityService
{
    protected function model(): string
    {
        return Variant::class;
    }

    protected function naturalKey(): array
    {
        return ['code', 'color_code'];
    }

    public function fields(): array
    {
        $keyvalue = fn (string $name) => Field::integer($name, 1)
            ->label(__("vehicle.fields.{$name}"))
            ->rules(Rule::exists('xlr8_utils_keyvalue', 'id')->whereNull('deleted_at'));

        return [
            Field::reference('segment_code', 'xlr8_vehicle_segment', 5)->label(__('vehicle.fields.segment_code'))->required(),
            Field::reference('sub_segment_code', 'xlr8_vehicle_subsegment', 20)->label(__('vehicle.fields.sub_segment_code')),
            Field::reference('model_code', 'xlr8_vehicle_model', 30)->label(__('vehicle.fields.model_code'))->required(),
            Field::code('code', 40)->label(__('vehicle.fields.code'))->format('Full OEM code (A-Z 0-9), max 40')
                ->required()->unique(['color_code'])->immutable(),
            Field::text('color')->label('Colour')->transform('strip_tags', 'trim_spaces', 'uppercase'),
            Field::code('color_code', 10)->label('Colour Code'),
            Field::name('oem_name')->label(__('vehicle.fields.oem_name'))->required(),
            Field::name('custom_name')->label(__('vehicle.fields.custom_name')),
            Field::name('display_name')->label(__('vehicle.fields.display_name')),
            Field::make('taxi_price')->label(__('vehicle.fields.taxi_price'))->format('YES / NO')
                ->transform('trim', 'uppercase')->rules('in:YES,NO')->required(),
            $keyvalue('permit_id'),
            $keyvalue('fuel_type_id'),
            $keyvalue('body_type_id'),
            $keyvalue('body_make_id'),
            $keyvalue('status_id'),
            Field::integer('seating_capacity', 1)->label(__('vehicle.fields.seating_capacity')),
            Field::integer('wheels', 1)->label(__('vehicle.fields.wheels')),
            Field::integer('gvw', 0)->label(__('vehicle.fields.gvw')),
            Field::text('cc_capacity')->label(__('vehicle.fields.cc_capacity')),
            Field::name('transmission')->label(__('vehicle.fields.transmission')),
            Field::text('drivetrain')->label(__('vehicle.fields.drivetrain'))->transform('uppercase'),
            Field::flag('is_csd', false)->label(__('vehicle.fields.is_csd')),
            Field::text('csd_index')->label(__('vehicle.fields.csd_index')),
            Field::flag('is_active')->label(__('vehicle.fields.is_active')),
        ];
    }

    protected function beforeCreate(array &$data): void
    {
        $this->assertHierarchy($data);
    }

    protected function beforeUpdate(Model $model, array &$data): void
    {
        $this->assertHierarchy(array_merge($model->only(['segment_code', 'sub_segment_code', 'model_code']), $data));
    }

    /**
     * The variant's segment / sub segment must be its model's.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertHierarchy(array $data): void
    {
        $model = VehicleModel::where('code', $data['model_code'] ?? null)->first(['segment_code', 'sub_segment_code']);
        if (! $model) {
            return; // model_code existence is a field rule
        }
        if (($data['segment_code'] ?? null) !== $model->segment_code) {
            $this->fail('segment_code', "Model {$data['model_code']} belongs to segment {$model->segment_code}.");
        }
        if ($model->sub_segment_code && ($data['sub_segment_code'] ?? null) !== $model->sub_segment_code) {
            $this->fail('sub_segment_code', "Model {$data['model_code']} belongs to sub segment {$model->sub_segment_code}.");
        }
    }
}

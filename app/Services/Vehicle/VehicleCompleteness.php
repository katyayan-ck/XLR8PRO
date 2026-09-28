<?php

declare(strict_types=1);

namespace App\Services\Vehicle;

use App\Models\Utilities\KeyValue\Keyvalue;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;

/**
 * The one completeness rule for a vehicle (variant) — DEC-073. Only a complete vehicle may be Active, be priced and be
 * calculated. Used by Detect, the Vehicle Info import, VariantService (activation gate), the price import and calculate.
 *
 * Always required: Segment, Sub Segment, Fuel, Seating, Wheels, Transmission, Drivetrain, Body Make, Body Type, GST%,
 * Permit, Taxi Price, Custom Model, Custom Variant, Display Name, Colour Name.
 * Conditional (user decision 28-09, amends spec v3.1.1 §3.3): Private + ICE → CC; Private + EV → Motor; Goods → GVW;
 * Passenger and Misc → none of CC / Motor / GVW.
 */
class VehicleCompleteness
{
    /** variant attribute => Vehicle Info column label (messages and the export's "Missing fields" column) */
    public const REQUIRED = [
        'segment_code' => 'Segment', 'sub_segment_code' => 'Sub Segment', 'fuel_type_id' => 'Fuel',
        'seating_capacity' => 'Seating', 'wheels' => 'Wheels', 'transmission' => 'Transmission',
        'drivetrain' => 'Drivetrain', 'body_make_id' => 'Body Make', 'body_type_id' => 'Body Type',
        'gst_percent' => 'GST%', 'permit_id' => 'Permit', 'taxi_price' => 'Taxi Price',
        'custom_model' => 'Custom Model', 'custom_name' => 'Custom Variant', 'display_name' => 'Display Name',
        'color' => 'Colour Name',
    ];

    public const CONDITIONAL = ['cc_capacity' => 'CC', 'motor' => 'Motor', 'gvw' => 'GVW'];

    /** @var array<int, string> keyvalue id => code (memoised per request) */
    private array $codes = [];

    /** @var array<string, ?string> model code => custom model name */
    private array $modelNames = [];

    public function isComplete(Variant $variant): bool
    {
        return $this->missing($variant) === [];
    }

    /**
     * Attributes still missing (keys of REQUIRED / CONDITIONAL).
     *
     * @return list<string>
     */
    public function missing(Variant $variant): array
    {
        $missing = [];
        foreach (array_keys(self::REQUIRED) as $field) {
            $value = $field === 'custom_model' ? $this->customModel($variant) : $variant->getAttribute($field);
            if ($this->blank($value)) {
                $missing[] = $field;
            }
        }

        $permit = $this->code($variant->permit_id);
        $isElectric = $this->isElectric($this->code($variant->fuel_type_id));
        if ($permit === 'PRIVATE') {
            $field = $isElectric ? 'motor' : 'cc_capacity';
            if ($this->blank($variant->getAttribute($field))) {
                $missing[] = $field;
            }
        } elseif ($permit === 'GOODS' && $this->blank($variant->gvw)) {
            $missing[] = 'gvw';
        }

        return $missing;
    }

    /** @return list<string> the missing fields as Vehicle Info column labels */
    public function missingLabels(Variant $variant): array
    {
        return array_map(fn (string $f) => self::REQUIRED[$f] ?? self::CONDITIONAL[$f] ?? $f, $this->missing($variant));
    }

    public function permitCode(Variant $variant): ?string
    {
        return $this->code($variant->permit_id);
    }

    public function isElectric(?string $fuelCode): bool
    {
        return $fuelCode !== null && (str_contains($fuelCode, 'ELECTRIC') || $fuelCode === 'EV' || str_contains($fuelCode, 'BEV'));
    }

    private function customModel(Variant $variant): ?string
    {
        if ($variant->relationLoaded('vehicleModel') && $variant->vehicleModel) {
            return $variant->vehicleModel->name;
        }
        $code = (string) $variant->model_code;
        if ($code === '') {
            return null;
        }

        return $this->modelNames[$code] ??= VehicleModel::query()->where('code', $code)->value('name');
    }

    private function code(mixed $keyvalueId): ?string
    {
        if ($this->blank($keyvalueId)) {
            return null;
        }
        $id = (int) $keyvalueId;
        if (! array_key_exists($id, $this->codes)) {
            $this->codes[$id] = strtoupper((string) Keyvalue::query()->whereKey($id)->value('code'));
        }

        return $this->codes[$id] !== '' ? $this->codes[$id] : null;
    }

    private function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}

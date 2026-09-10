<?php

namespace App\Imports;

use App\Facades\KeywordValue;
use App\Models\Core\Brand;
use App\Models\Core\Color;
use App\Models\Core\Segment;
use App\Models\Core\SubSegment;
use App\Models\Core\Variant;
use App\Models\Core\VehicleModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;

/**
 * VehicleDefineImporter - Import vehicle definitions from Excel
 * 
 * IMPORTANT: VehicleModel is unique by Brand_id only, not by Segment!
 * This means ALFA LOAD DUO should exist only ONCE per brand,
 * regardless of how many segments/variants it appears in.
 */
class VehicleDefineImporter implements ToCollection, WithCalculatedFormulas
{
    private $rowCount = 0;
    private $skipped = 0;
    private array $headings = [];
    private array $created = [
        'brands' => 0,
        'segments' => 0,
        'sub_segments' => 0,
        'vehicle_models' => 0,
        'variants' => 0,
        'colors' => 0,
        'variant_colors' => 0,
    ];
    private array $errors = [];

    public function headings(): array
    {
        return $this->headings;
    }

    public function collection(Collection $rows)
    {
        echo "\n═══════════════════════════════════════════════════════════════\n";
        echo "            VEHICLE DEFINITION IMPORT - STARTED\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";

        DB::beginTransaction();
        try {
            foreach ($rows as $key => $row) {
                $this->rowCount++;

                // Skip header row
                if ($key === 0) {
                    $this->headings = $row->toArray();
                    continue;
                }

                // Convert to associative array
                $data = $this->mapRowToArray($row);

                // Skip rows without Model Code
                if (empty($data['Model Code'])) {
                    $this->skipped++;
                    continue;
                }

                try {
                    $this->processRow($data);
                } catch (\Exception $e) {
                    $this->errors[] = [
                        'row' => $this->rowCount,
                        'model_code' => $data['Model Code'] ?? 'UNKNOWN',
                        'error' => $e->getMessage(),
                    ];
                    echo "❌ ROW {$this->rowCount}: {$data['Model Code']} - {$e->getMessage()}\n";
                }
            }

            DB::commit();
            $this->displaySummary();
        } catch (\Exception $e) {
            DB::rollBack();
            echo "\n❌ FATAL ERROR: {$e->getMessage()}\n";
            throw $e;
        }
    }

    private function mapRowToArray(Collection $row): array
    {
        $data = [];
        foreach ($this->headings as $index => $heading) {
            $data[$heading] = $row[$index] ?? null;
        }
        // print_r($data);
        return $data;
    }

    private function processRow(array $data): void
    {
        // 1. Brand
        $brand = $this->getOrCreateBrand();
        if (!$brand) {
            throw new \Exception("Brand creation failed");
        }

        // 2. Segment
        $segment = $this->getOrCreateSegment($brand, $data);
        if (!$segment) {
            throw new \Exception("Segment creation failed");
        }

        // 3. SubSegment (optional)
        $subSegment = null;
        if (!empty($data['Sub Segment'])) {
            $subSegment = $this->getOrCreateSubSegment($segment, $data);
        }

        // 4. VehicleModel - UNIQUE BY BRAND_ID ONLY!
        $vehicleModel = $this->getOrCreateVehicleModel($brand, $segment, $subSegment, $data);
        if (!$vehicleModel) {
            throw new \Exception("VehicleModel creation failed");
        }

        // 5. Color
        $color = $this->getOrCreateColor($brand, $segment, $subSegment, $vehicleModel, $data);
        if (!$color) {
            throw new \Exception("Color creation failed");
        }

        // 6. Variant
        $variant = $this->createVariant($brand, $segment, $subSegment, $vehicleModel, $data);
        if (!$variant) {
            throw new \Exception("Variant creation failed");
        }

        // 7. Variant-Color Mapping
        $this->createOrUpdateVariantColor($variant, $color);
    }

    private function getOrCreateBrand(): ?Brand
    {
        $brandName = "Mahindra";
        $brandCode = "MHND";

        $brand = Brand::where('code', strtoupper($brandCode))->first();

        if (!$brand) {
            $brand = Brand::create([
                'name' => json_encode(['en' => $brandName]),
                'code' => strtoupper($brandCode),
                'is_active' => true,
            ]);
            $this->created['brands']++;
        }

        return $brand;
    }

    private function getOrCreateSegment(Brand $brand, array $data): ?Segment
    {
        $segmentName = $data['Segment'] ?? null;

        if (empty($segmentName)) {
            return null;
        }

        // Auto-generate segment code
        $segmentCode = strtoupper(str_replace(' ', '', $segmentName));

        // Check if exists (brand + code)
        $segment = Segment::where('brand_id', $brand->id)
            ->where('code', $segmentCode)
            ->first();

        if ($segment) {
            return $segment;
        }

        // Create new
        $segment = Segment::create([
            'brand_id' => $brand->id,
            'name' => json_encode(['en' => trim($segmentName)]),
            'code' => $segmentCode,
            'is_active' => true,
        ]);

        $this->created['segments']++;
        return $segment;
    }

    private function getOrCreateSubSegment(Segment $segment, array $data): ?SubSegment
    {
        $subSegmentName = $data['Sub Segment'] ?? null;

        if (empty($subSegmentName)) {
            return null;
        }

        $subSegmentCode = strtoupper(str_replace(' ', '', $subSegmentName));

        // Check if exists
        $subSegment = SubSegment::where('segment_id', $segment->id)
            ->where('code', $subSegmentCode)
            ->first();

        if ($subSegment) {
            return $subSegment;
        }

        // Create new
        $subSegment = SubSegment::create([
            'segment_id' => $segment->id,
            'name' => json_encode(['en' => trim($subSegmentName)]),
            'code' => $subSegmentCode,
            'is_active' => true,
        ]);

        $this->created['sub_segments']++;
        return $subSegment;
    }

    /**
     * Get or create VehicleModel
     * 
     * CRITICAL: VehicleModel is unique by Brand_id ONLY!
     * Not by Segment. Multiple rows can have same model name
     * if they're the same brand (even different segments).
     * 
     * This prevents duplicates like:
     * ALFA LOAD DUO appearing 6 times with same brand_id+segment
     */
    private function getOrCreateVehicleModel(
        Brand $brand,
        Segment $segment,
        ?SubSegment $subSegment,
        array $data
    ): ?VehicleModel {
        $oemModel = $data['OEM Model'] ?? null;
        $customModel = $data['Custom Model'] ?? null;

        if (empty($oemModel)) {
            return null;
        }

        // Check if model exists BY BRAND ONLY
        // NOT by segment! This is the KEY FIX!
        $vehicleModel = VehicleModel::where('brand_id', $brand->id)
            ->where('segment_id', $segment->id)
            ->where('name', trim($oemModel))
            ->first();
        //print_r($vehicleModel);

        if ($vehicleModel) {
            // Model exists - update custom_name if provided and different
            if (!empty($customModel) && $vehicleModel->custom_name !== trim($customModel)) {
                $vehicleModel->update(['custom_name' => trim($customModel)]);
            }
            return $vehicleModel;
        }

        // Create new model
        $vehicleModel = VehicleModel::create([
            'brand_id' => $brand->id,
            'segment_id' => $segment->id,
            'sub_segment_id' => $subSegment?->id,
            'name' => trim($oemModel),
            'custom_name' => !empty($customModel) ? trim($customModel) : null,
            'is_active' => true,
        ]);

        $this->created['vehicle_models']++;
        return $vehicleModel;
    }

    private function getOrCreateColor(
        Brand $brand,
        Segment $segment,
        ?SubSegment $subSegment,
        VehicleModel $vehicleModel,
        array $data
    ): ?Color {
        $colorName = $data['Colour'] ?? null;
        $colorCode = $data['Colour Code'] ?? null;

        if ($colorName == "#N/A") {
            $colorName = $colorCode;
        }

        if (empty($colorName)) {
            return null;
        }

        $colorCodeUpper = strtoupper($colorCode ?? $colorName);

        // Check if exists (by model + code)
        $color = Color::where('vehicle_model_id', $vehicleModel->id)
            ->where('code', $colorCodeUpper)
            ->first();

        if ($color) {
            return $color;
        }

        // Create new
        $color = Color::create([
            'brand_id' => $brand->id,
            'segment_id' => $segment->id,
            'sub_segment_id' => $subSegment?->id,
            'vehicle_model_id' => $vehicleModel->id,
            'name' =>  trim($colorName),
            'code' => $colorCodeUpper,
            'is_active' => true,
        ]);

        $this->created['colors']++;
        return $color;
    }

    private function createVariant(
        Brand $brand,
        Segment $segment,
        ?SubSegment $subSegment,
        VehicleModel $vehicleModel,
        array $data
    ): ?Variant {
        $oemVariant = $data['OEM Variant'] ?? null;
        $customVariant = $data['Custom Variant'] ?? null;
        $oemCode = $data['Model Code'] ?? null;
        $tOemCode = substr(trim($oemCode), 0, -2);
        if (empty($oemVariant) || empty($oemCode)) {
            return null;
        }

        // Check if variant already exists by oem_code
        $existingVariant = Variant::where('oem_code', $tOemCode)->first();
        if ($existingVariant) {
            return $existingVariant;
        }

        $variantData = [
            'brand_id' => $brand->id,
            'segment_id' => $segment->id,
            'sub_segment_id' => $subSegment?->id,
            'vehicle_model_id' => $vehicleModel->id,
            'name' => trim($oemVariant),
            'custom_name' => !empty($customVariant) ? trim($customVariant) : null,
            'oem_code' => $tOemCode,
            'is_active' => true,
        ];

        // Add specs using KeywordValue service
        if (!empty($data['Fuel'])) {
            $fuelId = KeywordValue::findValueId('fuel_type', trim($data['Fuel']));
            if ($fuelId) {
                $variantData['fuel_type_id'] = $fuelId;
            }
        }

        if (!empty($data['Seating'])) {
            $variantData['seating_capacity'] = (int) $data['Seating'];
        }

        if (!empty($data['Wheels'])) {
            $variantData['wheels'] = (int) $data['Wheels'];
        }

        if (!empty($data['CC'])) {
            $variantData['cc_capacity'] = trim($data['CC']);
        }

        if (!empty($data['GVW'])) {
            $variantData['gvwr'] = (int) $data['GVW'];
        }

        if (!empty($data['Body Make'])) {
            $makeId = KeywordValue::findValueId('body_make', trim($data['Body Make']));
            if ($makeId) {
                $variantData['body_make_id'] = $makeId;
            }
        }

        if (!empty($data['Body Type'])) {
            $typeId = KeywordValue::findValueId('body_type', trim($data['Body Type']));
            if ($typeId) {
                $variantData['body_type_id'] = $typeId;
            }
        }

        if (!empty($data['Permit'])) {
            $permitId = KeywordValue::findValueId('permit', trim($data['Permit']));
            if ($permitId) {
                $variantData['permit_id'] = $permitId;
            }
        }

        if (empty($data['Status']))
            $data['Status'] = "ACTIVE";
        else
            $data['Status'] = trim(strtoupper($data['Status']));

        $statusId = KeywordValue::findValueId('vehicle_status', $data['Status']);
        if ($statusId) {
            $variantData['status_id'] = $statusId;
            if ($data['Status'] == "ACTIVE")
                $variantData['is_active'] = true;
            else
                $variantData['is_active'] = false;
        }
        //print_r($variantData);
        $variant = Variant::create($variantData);
        //print_r($variant->toarray());
        $this->created['variants']++;
        return $variant;
    }

    private function createOrUpdateVariantColor(Variant $variant, Color $color): void
    {
        // Check if mapping exists
        $mapping = DB::table('variant_colors')
            ->where('variant_id', $variant->id)
            ->where('color_id', $color->id)
            ->first();

        if (!$mapping) {
            DB::table('variant_colors')->insert([
                'variant_id' => $variant->id,
                'color_id' => $color->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->created['variant_colors']++;
        }
    }

    private function displaySummary(): void
    {
        echo "\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "                  IMPORT COMPLETED\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";

        echo "📊 SUMMARY:\n";
        echo "   Total Rows Processed:    {$this->rowCount}\n";
        echo "   Rows Skipped:            {$this->skipped}\n";
        echo "   Successfully Imported:   " . ($this->rowCount - $this->skipped) . "\n\n";

        if (count($this->errors) > 0) {
            echo "⚠️  ERRORS OCCURRED:\n";
            foreach ($this->errors as $error) {
                echo "   • Row {$error['row']} ({$error['model_code']}): {$error['error']}\n";
            }
            echo "\n";
        }

        echo "📈 CREATED:\n";
        echo "   Brands:                  {$this->created['brands']}\n";
        echo "   Segments:                {$this->created['segments']}\n";
        echo "   Sub-Segments:            {$this->created['sub_segments']}\n";
        echo "   Vehicle Models:          {$this->created['vehicle_models']}\n";
        echo "   Variants:                {$this->created['variants']}\n";
        echo "   Colors:                  {$this->created['colors']}\n";
        echo "   Variant-Color Mappings:  {$this->created['variant_colors']}\n\n";

        echo "═══════════════════════════════════════════════════════════════\n\n";
    }
}

<?php
namespace App\Imports;

// ── FIXED: was "use App\Models\Iam\Keyvalue;" ────────────────────
use App\Models\Utilities\KeyValue\Keyvalue;
use App\Models\Utilities\KeyValue\KeywordMaster;
// ─────────────────────────────────────────────────────────────────

use App\Models\Vehicle\{Brand, Segment, SubSegment, VehicleModel, Variant, Color};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\{
    ToCollection, WithHeadingRow, WithChunkReading, WithEvents
};
use Maatwebsite\Excel\Events\{BeforeImport, AfterImport};

class VehicleInfoImport implements ToCollection, WithHeadingRow, WithChunkReading, WithEvents
{
    public int $inserted = 0;
    public int $skipped  = 0;
    public array $errors  = [];

    /** Brand is fixed for a single import run. Multi-brand: pass as constructor param. */
    public function __construct(
        private readonly string $brandCode = 'MHD',
        private readonly string $brandName = 'Mahindra'
    ) {}

    public function chunkSize(): int { return 100; }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => fn() => $this->ensureBrand(),
        ];
    }

    // ── Entry point ───────────────────────────────────────────────

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            try {
                DB::transaction(fn() => $this->processRow($row->toArray(), $index + 2));
                $this->inserted++;
            } catch (\Throwable $e) {
                $this->skipped++;
                $this->errors[] = [
                    'row'        => $index + 2,
                    'model_code' => $row['model_code'] ?? 'N/A',
                    'error'      => $e->getMessage(),
                ];
                Log::warning("VehicleInfoImport row " . ($index + 2) . ": " . $e->getMessage());
            }
        }
    }

    // ── Per-row processor (Level 1→5 in order) ───────────────────

    private function processRow(array $row, int $rowNum): void
    {
        $modelCode     = strtoupper(trim($row['model_code'] ?? ''));
        $oemVariant    = trim($row['oem_variant'] ?? '');
        $customModel   = strtoupper(trim($row['custom_model'] ?? ''));
        $customVariant = trim($row['custom_variant'] ?? '');
        $segmentRaw    = strtoupper(trim($row['segment'] ?? ''));
        $subSegRaw     = strtoupper(trim($row['sub_segment'] ?? ''));
        $colourCode    = strtoupper(trim($row['colour_code'] ?? Color::codeFromModelCode($modelCode)));
        $colourName    = trim($row['colour'] ?? '');

        if (empty($modelCode) || strlen($modelCode) < 4) {
            throw new \RuntimeException("Invalid or missing Model Code");
        }

        // ── Level 1: Segment ──────────────────────────────────────
        $segCode = Segment::generateCode($segmentRaw);
        $segment = Segment::firstOrCreate(
            ['brand_code' => $this->brandCode, 'code' => $segCode],
            ['name' => ucwords(strtolower($segmentRaw)), 'is_active' => true]
        );

        // ── Level 2: SubSegment (nullable) ────────────────────────
        $subSegCode = null;
        if (!empty($subSegRaw) && !in_array($subSegRaw, ['', '0', 'NULL', 'ANY'])) {
            $subSegCode = SubSegment::generateCode($subSegRaw);
            SubSegment::firstOrCreate(
                ['segment_code' => $segCode, 'code' => $subSegCode],
                [
                    'brand_code' => $this->brandCode,
                    'name'       => ucwords(strtolower($subSegRaw)),
                    'is_active'  => true,
                ]
            );
        }

        // ── Level 3: VehicleModel ─────────────────────────────────
        $modelDbCode = VehicleModel::generateCode($customModel);
        VehicleModel::firstOrCreate(
            ['code' => $modelDbCode],
            [
                'brand_code'       => $this->brandCode,
                'segment_code'     => $segCode,
                'sub_segment_code' => $subSegCode,
                'name'             => $customModel,
                'custom_name'      => $customModel,
                'is_active'        => true,
            ]
        );

        // ── Level 4: KKV lookups ──────────────────────────────────
        $permitId   = $this->kkv('permit',        strtolower($row['permit']    ?? 'private'));
        $fuelTypeId = $this->kkv('fuel_type',      strtolower($row['fuel']      ?? ''));
        $bodyMakeId = $this->kkv('body_make',      strtolower($row['body_make'] ?? ''));
        $bodyTypeId = $this->kkv('body_type',      strtolower($row['body_type'] ?? ''));
        $statusId   = $this->kkv('vehicle_status', strtolower($row['status']    ?? 'active'));

        // ── Level 5: Variant ──────────────────────────────────────
        $variantCode = Variant::codeFromModelCode($modelCode);
        Variant::firstOrCreate(
            ['code' => $variantCode],
            [
                'brand_code'       => $this->brandCode,
                'segment_code'     => $segCode,
                'sub_segment_code' => $subSegCode,
                'model_code'       => $modelDbCode,
                'name'             => $oemVariant,
                'custom_name'      => $customVariant,
                'oem_code'         => null,
                'seating_capacity' => (int) ($row['seating'] ?? 0) ?: null,
                'wheels'           => (int) ($row['wheels']  ?? 4),
                'gvw'              => (int) ($row['gvw']     ?? 0) ?: null,
                'transmission'     => strtoupper($row['transmission'] ?? ''),
                'drivetrain'       => strtoupper($row['drivetrain']   ?? ''),
                'permit_id'        => $permitId,
                'fuel_type_id'     => $fuelTypeId,
                'body_make_id'     => $bodyMakeId,
                'body_type_id'     => $bodyTypeId,
                'status_id'        => $statusId,
                'is_active'        => strtolower($row['status'] ?? 'active') === 'active',
            ]
        );

        // ── Level 6: Color ────────────────────────────────────────
        Color::firstOrCreate(
            ['model_code' => $modelDbCode, 'code' => $colourCode],
            [
                'brand_code'       => $this->brandCode,
                'segment_code'     => $segCode,
                'sub_segment_code' => $subSegCode,
                'name'             => $colourName,
                'is_active'        => true,
            ]
        );

        // ── Level 7: variant_colors pivot ─────────────────────────
        $variant = Variant::where('code', $variantCode)->first();
        $color   = Color::where('model_code', $modelDbCode)->where('code', $colourCode)->first();
        if ($variant && $color) {
            DB::table('variant_colors')->insertOrIgnore([
                'variant_id' => $variant->id,
                'color_id'   => $color->id,
            ]);
        }
    }

    // ── Brand bootstrap ───────────────────────────────────────────

    private function ensureBrand(): void
    {
        Brand::firstOrCreate(
            ['code' => $this->brandCode],
            ['name' => $this->brandName, 'is_active' => true]
        );
    }

    // ── KKV upsert helper ─────────────────────────────────────────
    // FIXED: old version used non-existent 'group' column directly on Keyvalue.
    // Correct structure: keyword_master_id (from xlr8_utils_keyword_master)
    //                  + key + value (in xlr8_utils_keyvalue)

    private function kkv(string $keyword, string $value): ?int
    {
        if (empty($value)) return null;

        // Step 1: resolve keyword_master_id (creates master record if missing)
        $master = KeywordMaster::firstOrCreate(
            ['keyword' => $keyword],
            ['details' => ucwords(str_replace('_', ' ', $keyword)), 'status' => 1]
        );

        // Step 2: resolve keyvalue record under that master
        $record = Keyvalue::firstOrCreate(
            [
                'keyword_master_id' => $master->id,
                'key'               => $value,
                'parent_id'         => null,
            ],
            [
                'value'  => ucwords(str_replace(['_', '-'], ' ', $value)),
                'level'  => 0,
                'status' => 1,
            ]
        );

        return $record->id;
    }

    // ── Import summary ────────────────────────────────────────────

    public function getSummary(): array
    {
        return [
            'inserted' => $this->inserted,
            'skipped'  => $this->skipped,
            'errors'   => $this->errors,
        ];
    }
}

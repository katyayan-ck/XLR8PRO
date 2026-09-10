<?php

/**
 * Path: app/Services/Vehicle/Pricing/VehicleInfoExportService.php
 *
 * All variants (complete + stubs) + session profiles.
 * Model Code column = OEM Code = variant.code (do NOT append color).
 * Sorted Segment → OEM Model → OEM Code.
 */

namespace App\Services\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\ImportSession;
use App\Models\Vehicle\Pricing\Profile;
use App\Services\Vehicle\VehicleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class VehicleInfoExportService
{
    public function __construct(
        protected SheetHeaderService $headers,
        protected VehicleService $vehicles
    ) {}

    /**
     * @return array{path: string, filename: string, row_count: int}
     */
    public function exportForSession(ImportSession $session): array
    {
        $rows = $this->buildAllRows($session);

        usort($rows, function (array $a, array $b) {
            $s = strcasecmp((string) ($a['segment'] ?? ''), (string) ($b['segment'] ?? ''));
            if ($s !== 0) {
                return $s;
            }
            $m = strcasecmp((string) ($a['oem_model'] ?? ''), (string) ($b['oem_model'] ?? ''));
            if ($m !== 0) {
                return $m;
            }
            return strcasecmp((string) ($a['model_code'] ?? ''), (string) ($b['model_code'] ?? ''));
        });

        $columns = $this->columnMap();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Vehicle Info');

        $col = 1;
        foreach ($columns as $label) {
            $sheet->setCellValueByColumnAndRow($col, 1, $label);
            $col++;
        }

        $lastColCoord = $sheet->getCellByColumnAndRow(count($columns), 1)->getCoordinate();
        $sheet->getStyle('A1:' . $lastColCoord)->getFont()->setBold(true);
        $sheet->getStyle('A1:' . $lastColCoord)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E79');
        $sheet->getStyle('A1:' . $lastColCoord)->getFont()->getColor()->setRGB('FFFFFF');

        $rowNum = 2;
        $fieldOrder = array_keys($columns);
        foreach ($rows as $data) {
            $col = 1;
            foreach ($fieldOrder as $fieldCode) {
                $sheet->setCellValueByColumnAndRow($col, $rowNum, $data[$fieldCode] ?? '');
                $col++;
            }
            $rowNum++;
        }

        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columns));
        foreach ($rows as $i => $data) {
            if (($data['is_vehicle_master_complete'] ?? 'N') === 'N') {
                $excelRow = $i + 2;
                $sheet->getStyle('A' . $excelRow . ':' . $lastColLetter . $excelRow)
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFF2CC');
            }
        }

        $dir = storage_path('app/pricing-exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = 'Vehicle_Info_' . $session->id . '_' . now()->format('Y-m-d_His') . '.xlsx';
        $path = $dir . DIRECTORY_SEPARATOR . $filename;

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        Log::info('[VehicleInfoExport] exported', [
            'session_id' => $session->id,
            'rows'       => $rowNum - 2,
            'path'       => $path,
        ]);

        return [
            'path'      => $path,
            'filename'  => $filename,
            'row_count' => $rowNum - 2,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildAllRows(ImportSession $session): array
    {
        $kkv = $this->loadKeyValues();
        $modelsByCode = $this->loadModels();
        $metaByCode = $this->loadDetectMeta($session->id);
        $byCode = [];

        $variants = DB::table('xlr8_vehicle_variant')
            ->whereNull('deleted_at')
            ->orderBy('segment_code')
            ->orderBy('model_code')
            ->orderBy('code')
            ->get();

        foreach ($variants as $v) {
            $oemCode = strtoupper(trim((string) $v->code));
            $modelMeta = $modelsByCode[strtoupper((string) $v->model_code)] ?? null;
            $oemModel = $modelMeta['oem_name'] ?? $modelMeta['name'] ?? (string) $v->model_code;
            $variant = new \App\Models\Vehicle\Variant();
            $variant->forceFill((array) $v);
            $complete = $this->vehicles->isComplete($variant);

            $byCode[$oemCode] = [
                'model_code'                 => $oemCode,
                'oem_model'                  => $oemModel,
                'oem_variant'                => $v->oem_name ?? '',
                'segment'                    => $v->segment_code ?? '',
                'sub_segment'                => $v->sub_segment_code ?? '',
                'fuel_type'                  => $kkv[(int) ($v->fuel_type_id ?? 0)] ?? '',
                'seating'                    => $v->seating_capacity ?? '',
                'wheels'                     => $v->wheels ?? '',
                'transmission'               => $v->transmission ?? '',
                'drivetrain'                 => $v->drivetrain ?? '',
                'body_make'                  => $kkv[(int) ($v->body_make_id ?? 0)] ?? '',
                'body_type'                  => $kkv[(int) ($v->body_type_id ?? 0)] ?? '',
                'cc_or_power'                => $v->cc_capacity ?? '',
                'motor'                      => $v->motor ?? '',
                'gvw'                        => $v->gvw ?? '',
                'gst_pct'                    => $v->gst_percent ?? '',
                'permit'                     => $kkv[(int) ($v->permit_id ?? 0)] ?? '',
                'taxi_price'                 => strtoupper((string) ($v->taxi_price ?? 'NO')),
                'custom_model'               => $modelMeta['name'] ?? $oemModel,
                'custom_variant'             => $v->custom_name ?? '',
                'display_name'               => $v->display_name ?? '',
                'colour_name'                => $v->color ?: ($v->color_code ?? ''),
                'status'                     => $v->is_active ? 'ACTIVE' : 'INACTIVE',
                'shield_pack'                => $v->shield_pack ?? '',
                'is_incomplete'              => $complete ? 'N' : 'Y',
                'is_vehicle_master_complete' => $complete ? 'Y' : 'N',
                'is_disabled'                => $v->is_active ? 'N' : 'Y',
            ];
        }

        $profiles = Profile::query()->orderBy('segment')->orderBy('model_code')->get();
        foreach ($profiles as $profile) {
            $code = strtoupper((string) $profile->model_code);
            if ($code === '') {
                continue;
            }
            if (isset($byCode[$code])) {
                if (! $profile->is_vehicle_master_complete) {
                    $byCode[$code]['is_incomplete'] = 'Y';
                    $byCode[$code]['is_vehicle_master_complete'] = 'N';
                }
                continue;
            }

            $meta = $metaByCode[$code] ?? [];
            $colorCode = $meta['color_code'] ?? (strlen($code) >= 2 ? substr($code, -2) : '');

            $byCode[$code] = [
                'model_code'                 => $code,
                'oem_model'                  => $meta['oem_model'] ?? '',
                'oem_variant'                => $meta['oem_variant'] ?? '',
                'segment'                    => $profile->segment ?? '',
                'sub_segment'                => $profile->segment ?? '',
                'fuel_type'                  => '',
                'seating'                    => '',
                'wheels'                     => '',
                'transmission'               => '',
                'drivetrain'                 => '',
                'body_make'                  => '',
                'body_type'                  => '',
                'cc_or_power'                => '',
                'motor'                      => '',
                'gvw'                        => '',
                'gst_pct'                    => '',
                'permit'                     => '',
                'taxi_price'                 => 'NO',
                'custom_model'               => $meta['oem_model'] ?? '',
                'custom_variant'             => $meta['oem_variant'] ?? '',
                'display_name'               => '',
                'colour_name'                => $colorCode,
                'status'                     => 'INACTIVE',
                'shield_pack'                => '',
                'is_incomplete'              => 'Y',
                'is_vehicle_master_complete' => 'N',
                'is_disabled'                => 'Y',
            ];
        }

        return array_values($byCode);
    }

    /** @return array<string, string> */
    protected function columnMap(): array
    {
        return [
            'model_code'                 => 'Model Code',
            'oem_model'                  => 'OEM Model',
            'oem_variant'                => 'OEM Variant',
            'segment'                    => 'Segment',
            'sub_segment'                => 'Sub Segment',
            'fuel_type'                  => 'Fuel',
            'seating'                    => 'Seating',
            'wheels'                     => 'Wheels',
            'transmission'               => 'Transmission',
            'drivetrain'                 => 'Drivetrain',
            'body_make'                  => 'Body Make',
            'body_type'                  => 'Body Type',
            'cc_or_power'                => 'CC',
            'motor'                      => 'Motor',
            'gvw'                        => 'GVW',
            'gst_pct'                    => 'GST%',
            'permit'                     => 'Permit',
            'taxi_price'                 => 'Taxi Price',
            'custom_model'               => 'Custom Model',
            'custom_variant'             => 'Custom Variant',
            'display_name'               => 'Display Name',
            'colour_name'                => 'Colour Name',
            'status'                     => 'Status',
            'shield_pack'                => 'Shield Pack',
            'is_incomplete'              => 'Is Incomplete',
            'is_vehicle_master_complete' => 'Master Complete (Y/N)',
            'is_disabled'                => 'Inactive (Y/N)',
        ];
    }

    /** @return array<int, string> */
    protected function loadKeyValues(): array
    {
        $map = [];
        try {
            $table = null;
            foreach (['xlr8_utilities_keyvalues', 'xlr8_keyvalues', 'keyvalues', 'xlr8_keyvalue'] as $candidate) {
                if (DB::getSchemaBuilder()->hasTable($candidate)) {
                    $table = $candidate;
                    break;
                }
            }
            if ($table === null) {
                return [];
            }
            $rows = DB::table($table)->select('id', 'value')->get();
            foreach ($rows as $r) {
                $map[(int) $r->id] = (string) ($r->value ?? '');
            }
        } catch (\Throwable $e) {
            Log::warning('[VehicleInfoExport] keyvalue load failed', ['error' => $e->getMessage()]);
        }

        return $map;
    }

    /** @return array<string, array{name?:string,oem_name?:string}> */
    protected function loadModels(): array
    {
        $out = [];
        try {
            $rows = DB::table('xlr8_vehicle_model')
                ->whereNull('deleted_at')
                ->get(['code', 'name', 'oem_name']);
            foreach ($rows as $r) {
                $out[strtoupper((string) $r->code)] = [
                    'name'     => $r->name,
                    'oem_name' => $r->oem_name,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('[VehicleInfoExport] model load failed', ['error' => $e->getMessage()]);
        }

        return $out;
    }

    /** @return array<string, array{oem_model?:?string,oem_variant?:?string,color_code?:string}> */
    protected function loadDetectMeta(int $sessionId): array
    {
        $metaByCode = [];
        $flagRows = DB::table('xlr8_vehicle_pricing_change_flags')
            ->where('import_session_id', $sessionId)
            ->where('change_type', 'vehicle_master')
            ->where('field_name', 'new_vehicle')
            ->get(['model_code', 'new_value']);

        foreach ($flagRows as $fr) {
            $code = strtoupper((string) $fr->model_code);
            $decoded = json_decode((string) $fr->new_value, true);
            $metaByCode[$code] = is_array($decoded) ? $decoded : [
                'color_code' => strlen($code) >= 2 ? substr($code, -2) : '',
            ];
        }

        return $metaByCode;
    }
}

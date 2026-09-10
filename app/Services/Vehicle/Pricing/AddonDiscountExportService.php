<?php

/**
 * Path: app/Services/Vehicle/Pricing/AddonDiscountExportService.php
 *
 * Export ONLY stored active rows.
 * Do NOT seed a zero row per vehicle_model — that would override Segment+Any.
 * Model rows appear only when the workbook/DB actually has an override.
 */

namespace App\Services\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\Addon;
use App\Models\Vehicle\Pricing\DealerCharge;
use App\Models\Vehicle\Pricing\Discount;
use App\Models\Vehicle\Pricing\ImportSession;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AddonDiscountExportService
{
    /**
     * @return array{path:string,filename:string}
     */
    public function exportForSession(ImportSession $session): array
    {
        $plog = new PricingProcessLogger($session->id);
        $ss = new Spreadsheet();
        $ss->removeSheetByIndex(0);

        $this->sheet($ss, 'Dealer Charges', [
            'Segment', 'Permit', 'Model',
            'Incidental Charges', 'FastTag', 'TRC', 'RTO Tape', 'COD Charges',
        ], $this->dealerRows());

        $this->sheet($ss, 'RSA', [
            'Segment', 'Model', 'Standard Coverage',
            'Std + 1 Year', 'Std + 2 Years', 'Std + 3 Years', 'Std + 4 Years', 'Std + 5 Years',
        ], $this->rsaRows());

        $this->sheet($ss, 'Shield', [
            'OEM Model', 'OEM Variant', 'Shield Pack', 'Transmission', 'Fuel',
            'Standard Warranty',
            'Shield Scheme 1 Name', 'Shield Scheme 1 Amt',
            'Shield Scheme 2 Name', 'Shield Scheme 2 Amt',
        ], $this->shieldRows());

        $this->sheet($ss, 'Exchange', [
            'Model', 'Variant', 'Scheme Type', 'OEM Share', 'Dealer Share', 'Total',
        ], $this->discountRows('EXCHANGE'));

        $this->sheet($ss, 'Corporate', [
            'Model', 'Variant', 'Category', 'OEM Share', 'Dealer Share', 'Total',
        ], $this->discountRows('CORPORATE'));

        $dir = storage_path('app/pricing-exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = 'Addon_N_Discounts_' . $session->id . '_' . now()->format('Y-m-d_His') . '.xlsx';
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        (new Xlsx($ss))->save($path);
        $ss->disconnectWorksheets();

        $plog->info('Addon export written', ['filename' => $filename]);

        return compact('path', 'filename');
    }

    protected function sheet(Spreadsheet $ss, string $title, array $headers, array $rows): void
    {
        $ws = $ss->createSheet();
        $ws->setTitle($title);
        foreach ($headers as $i => $h) {
            $ws->setCellValueByColumnAndRow($i + 1, 1, $h);
        }
        $last = $ws->getCellByColumnAndRow(count($headers), 1)->getCoordinate();
        $ws->getStyle('A1:' . $last)->getFont()->setBold(true);
        $ws->getStyle('A1:' . $last)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E79');
        $ws->getStyle('A1:' . $last)->getFont()->getColor()->setRGB('FFFFFF');

        $r = 2;
        foreach ($rows as $row) {
            foreach ($row as $c => $val) {
                $ws->setCellValueByColumnAndRow($c + 1, $r, $val);
            }
            $r++;
        }
    }

    protected function dealerRows(): array
    {
        if (! Schema::hasTable('xlr8_vehicle_pricing_dealer_charges')) {
            return [];
        }
        $grouped = [];
        foreach (DealerCharge::query()->active()->orderBy('id')->get() as $row) {
            $model = $this->displayScope($row->model_code);
            $k = strtoupper(implode('|', [(string) $row->segment, (string) $row->permit, $model]));
            $grouped[$k] ??= [$row->segment, $row->permit, $model, 0, 0, 0, 0, 0];
            if ($row->incidental !== null || $row->fastag !== null || $row->trc !== null) {
                $grouped[$k][3] = $row->incidental ?? 0;
                $grouped[$k][4] = $row->fastag ?? 0;
                $grouped[$k][5] = $row->trc ?? 0;
                $grouped[$k][6] = $row->rto_tape ?? 0;
                $grouped[$k][7] = $row->cod ?? 0;
            } elseif ($row->charge_code) {
                $idx = match (strtolower((string) $row->charge_code)) {
                    'incidental_charges', 'incidental' => 3,
                    'fast_tag', 'fastag' => 4,
                    'trc' => 5,
                    'rto_tape' => 6,
                    'cod_charges', 'cod' => 7,
                    default => null,
                };
                if ($idx !== null) {
                    $grouped[$k][$idx] = $row->amount;
                }
            }
        }

        return array_values($grouped);
    }

    protected function rsaRows(): array
    {
        if (! Schema::hasTable('xlr8_vehicle_pricing_addons')) {
            return [];
        }
        $grouped = [];
        foreach (Addon::query()->active()->ofType('RSA')->orderBy('id')->get() as $row) {
            $model = $this->displayScope($row->model_code);
            $k = strtoupper(implode('|', [(string) $row->segment, $model]));
            $grouped[$k] ??= [$row->segment, $model, $row->name, '', '', '', '', ''];
            $y = (int) $row->tenure_years;
            if ($y >= 1 && $y <= 5) {
                $grouped[$k][$y + 2] = $row->amount;
            }
        }

        return array_values($grouped);
    }

    protected function shieldRows(): array
    {
        if (! Schema::hasTable('xlr8_vehicle_pricing_addons')) {
            return [];
        }
        $grouped = [];
        foreach (Addon::query()->active()->ofType('SHIELD')->orderBy('id')->get() as $row) {
            $k = strtoupper(implode('|', [
                (string) $row->model_code,
                (string) $row->variant_code,
                (string) ($row->shield_pack ?? ''),
                (string) ($row->transmission ?? ''),
                (string) ($row->fuel ?? ''),
            ]));
            $grouped[$k] ??= [
                $this->displayScope($row->model_code),
                $this->displayScope($row->variant_code),
                $this->displayScope($row->shield_pack) === 'Any' ? 'ANY' : $row->shield_pack,
                $this->displayScope($row->transmission) === 'Any' ? 'ANY' : $row->transmission,
                $this->displayScope($row->fuel) === 'Any' ? 'ANY' : $row->fuel,
                '',
                '', '',
                '', '',
            ];
            if ($grouped[$k][6] === '') {
                $grouped[$k][6] = $row->scheme_name ?: $row->name;
                $grouped[$k][7] = $row->amount;
            } elseif ($grouped[$k][8] === '') {
                $grouped[$k][8] = $row->scheme_name ?: $row->name;
                $grouped[$k][9] = $row->amount;
            }
        }

        return array_values($grouped);
    }

    protected function discountRows(string $type): array
    {
        if (! Schema::hasTable('xlr8_vehicle_pricing_discounts')) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach (Discount::query()->active()->ofType($type)->orderBy('id')->get() as $row) {
            $label = $type === 'CORPORATE'
                ? ($row->category ?? $row->discount_category ?? $row->name)
                : ($row->scheme_name ?? $row->name);
            $key = strtoupper(implode('|', [
                (string) $row->model_code,
                (string) $row->variant_code,
                (string) $label,
            ]));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [
                $this->displayScope($row->model_code),
                $this->displayScope($row->variant_code),
                $label,
                $row->oem_share,
                $row->dealer_share,
                $row->amount ?? $row->total_discount,
            ];
        }

        return $out;
    }

    protected function displayScope(?string $v): string
    {
        $t = trim((string) $v);
        if ($t === '' || strcasecmp($t, 'ANY') === 0 || strcasecmp($t, 'ALL') === 0) {
            return 'Any';
        }

        return $t;
    }
}

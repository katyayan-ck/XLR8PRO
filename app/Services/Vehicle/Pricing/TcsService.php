<?php

/**
 * Path: app/Services/Vehicle/Pricing/TcsService.php
 */

namespace App\Services\Vehicle\Pricing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TcsService
{
    /**
     * @return array{limit:float,rate:float}
     */
    public function config(): array
    {
        $limit = (float) config('pricing.tcs_limit', 1000000);
        $rate  = (float) config('pricing.tcs_rate', 1);

        try {
            if (Schema::hasTable('xlr8_vehicle_pricing_tcs_config')) {
                $row = DB::table('xlr8_vehicle_pricing_tcs_config')
                    ->whereNull('deleted_at')
                    ->orderByDesc('id')
                    ->first();
                if ($row) {
                    if (isset($row->limit_amount)) {
                        $limit = (float) $row->limit_amount;
                    } elseif (isset($row->limit)) {
                        $limit = (float) $row->limit;
                    }
                    if (isset($row->rate_percent)) {
                        $rate = (float) $row->rate_percent;
                    } elseif (isset($row->rate)) {
                        $rate = (float) $row->rate;
                    }
                }
            }
        } catch (\Throwable $e) {
            // keep config defaults
        }

        return ['limit' => $limit, 'rate' => $rate];
    }

    /**
     * TCS applies when invoice exceeds limit. Amount = invoice * rate/100.
     */
    public function compute(float $invoiceValue): array
    {
        $cfg = $this->config();
        $applicable = $invoiceValue > $cfg['limit'];
        $amount = $applicable
            ? round($invoiceValue * ($cfg['rate'] / 100), 2)
            : 0.0;

        return [
            'limit'      => $cfg['limit'],
            'rate'       => $cfg['rate'],
            'applicable' => $applicable,
            'amount'     => $amount,
        ];
    }
}

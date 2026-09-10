<?php

/**
 * Path: app/Services/Vehicle/Pricing/PricingEngineService.php
 *
 * SSOT for on-road JSON. Always returns PricingJsonContract keys.
 * Incomplete masters are flagged and not published as active snapshots.
 */

namespace App\Services\Vehicle\Pricing;

use App\Models\Vehicle\Pricing\ChangeFlag;
use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Pricing\Profile;
use App\Models\Vehicle\Pricing\Snapshot;
use App\Models\Vehicle\Variant;
use App\Services\Vehicle\AccessoryService;
use App\Services\Vehicle\VehicleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PricingEngineService
{
    public function __construct(
        protected VehicleService $vehicles,
        protected TcsService $tcs,
        protected RtoService $rto,
        protected InsuranceService $insurance,
        protected AccessoryService $accessories
    ) {}

        public function getPricingPayload(string $oemCode, array $options = []): array
    {
        $vin = strtolower((string) ($options['vin_type'] ?? 'nv'));
        if ($vin === 'current' || $vin === 'new') {
            $vin = 'nv';
        }
        if ($vin === 'old') {
            $vin = 'ov';
        }

        return $this->build(
            $oemCode,
            (string) ($options['channel'] ?? 'normal'),
            in_array($vin, ['nv', 'ov'], true) ? $vin : 'nv',
            $options['wef_date'] ?? null,
            $options['permit'] ?? null
        );
    }

    /**
     * @return array{payload:array, published:bool, errors:list<string>}
     */
    public function calculateAndPublish(
        string $oemCode,
        string $channel = 'normal',
        string $vinType = 'nv',
        ?string $wefDate = null,
        ?int $sessionId = null,
        ?int $userId = null,
        ?string $permitOverride = null
    ): array {
        $payload = $this->build($oemCode, $channel, $vinType, $wefDate, $permitOverride);
        $errors = $payload['errors'] ?? [];

        if ($payload['incomplete'] || $payload['hold']) {
            return ['payload' => $payload, 'published' => false, 'errors' => $errors];
        }

        $this->persistSnapshot($payload, $oemCode, $channel, $vinType, $wefDate, $sessionId, $userId);

        if ($sessionId) {
            ChangeFlag::query()
                ->where('import_session_id', $sessionId)
                ->where('model_code', $oemCode)
                ->where('is_processed', false)
                ->update([
                    'is_processed' => true,
                    'updated_by'   => $userId,
                    'updated_at'   => now(),
                ]);
        }

        return ['payload' => $payload, 'published' => true, 'errors' => $errors];
    }

    /**
     * @param  string|null  $permitOverride  Force RTO/Insurance permit (taxi Private vs Passenger).
     */
    public function build(
        string $oemCode,
        string $channel = 'normal',
        string $vinType = 'nv',
        ?string $wefDate = null,
        ?string $permitOverride = null
    ): array {
        $oemCode = strtoupper(preg_replace('/\s+/', '', $oemCode) ?? $oemCode);
        $json = PricingJsonContract::empty($oemCode, $channel, $vinType);
        $json['wef_date'] = $wefDate;

        $variant = Variant::query()->where('code', $oemCode)->first();
        if (! $variant) {
            $json['errors'][] = 'Variant not found';
            $json['incomplete'] = true;
            return $json;
        }

        $complete = $this->vehicles->isComplete($variant);
        $json['incomplete'] = ! $complete;
        $json['segment'] = $variant->segment_code;
        $json['model_code'] = $variant->model_code;
        $json['display_name'] = $variant->display_name;
        $json['permit'] = $permitOverride
            ? strtoupper(trim($permitOverride))
            : $this->kv($variant->permit_id);
        $json['fuel'] = $this->kv($variant->fuel_type_id);
        $json['taxi_price'] = strtoupper((string) ($variant->taxi_price ?? 'NO'));

        if ($this->isHeld($variant->segment_code)) {
            $json['hold'] = true;
            $json['errors'][] = 'Price list on hold for segment ' . $variant->segment_code;
        }

        $price = Pricing::query()
            ->where('model_code', $oemCode)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->when($wefDate, fn ($q) => $q->whereDate('wef_date', '<=', $wefDate))
            ->orderByDesc('wef_date')
            ->first();

        if (! $price) {
            $json['errors'][] = 'No active OEM price row';
            return $json;
        }

        $json['wef_date'] = optional($price->wef_date)->format('Y-m-d') ?? $wefDate;
        $json['ex_showroom'] = (float) $price->ex_showroom_price;
        $json['assessable_value'] = (float) ($price->assessable_value_with_freight ?? 0);
        $json['gst_percent'] = (float) ($price->gst_percent ?? $variant->gst_percent ?? 0);
        $json['gst_amount'] = (float) ($price->gst_amount ?? 0);
        $json['mm_invoice'] = (float) ($price->mm_invoice_amount ?? 0);
        $json['dealer_margin'] = (float) ($price->dealer_margin ?? 0);

        $json['discounts']['oem_scheme'] = (float) ($price->curr_oem_scheme ?? 0);
        $json['discounts']['dealer_cont'] = (float) ($price->curr_dealer_cont ?? 0);
        $json['discounts']['cash'] = (float) ($price->curr_cash_discount ?? 0);
        $json['discounts']['accessory'] = (float) ($price->curr_acc_discount ?? 0);
        $json['discounts']['shield'] = (float) ($price->curr_shield_discount ?? 0);

        $json['dealer_charges'] = $this->dealerCharges($variant, $json['permit']);
        $json['rsa'] = $this->addonOptions($variant, 'RSA', 1);
        $json['shield'] = $this->addonOptions($variant, 'SHIELD', null);
        $json['discounts']['corporate'] = $this->discountOptions($variant, 'CORPORATE');
        $json['discounts']['exchange'] = $this->discountOptions($variant, 'EXCHANGE');

        $charges = (float) $json['dealer_charges']['total']
            + (float) $json['rsa']['selected_amount']
            + (float) $json['shield']['selected_amount'];

        $less = (float) $json['discounts']['oem_scheme']
            + (float) $json['discounts']['dealer_cont']
            + (float) $json['discounts']['cash']
            + (float) $json['discounts']['accessory']
            + (float) $json['discounts']['shield']
            + (float) $json['discounts']['rsa'];

        $invoice = round((float) $json['ex_showroom'] + $charges - $less, 2);
        $json['invoice_value'] = $invoice;

        $json['tcs'] = $this->tcs->compute($invoice);

        $json['rto'] = $this->rto->quote([
            'segment'     => $variant->segment_code,
            'model'       => $variant->model_code,
            'variant'     => $variant->code,
            'permit'      => $json['permit'],
            'wheels'      => $variant->wheels,
            'fuel'        => $json['fuel'],
            'gvw'         => $variant->gvw,
            'ex_showroom' => $json['ex_showroom'],
        ]);

        $json['insurance'] = $this->insurance->quote([
            'segment' => $variant->segment_code,
            'permit'  => $json['permit'],
            'fuel'    => $json['fuel'],
            'wheels'  => $variant->wheels,
            'cc'      => $variant->cc_capacity,
            'gvw'     => $variant->gvw,
            'seating' => $variant->seating_capacity,
            'invoice' => $invoice,
        ]);

        try {
            $packs = $this->accessories->listForVehicle(
                (string) $variant->segment_code,
                (string) $variant->model_code,
                (string) $variant->code,
                (string) ($json['permit'] ?? '')
            );
            $json['accessories']['packs'] = $packs;
            $json['accessories']['total'] = round(array_sum(array_map(
                fn ($p) => (float) ($p['mrp'] ?? $p['price'] ?? $p['amount'] ?? 0),
                is_array($packs) ? $packs : []
            )), 2);
        } catch (\Throwable $e) {
            $json['errors'][] = 'Accessories: ' . $e->getMessage();
        }

        $onRoad = round(
            $invoice
            + (float) $json['insurance']['selected_total']
            + (float) $json['rto']['total']
            + (float) $json['tcs']['amount'],
            2
        );
        $json['on_road'] = $onRoad;
        $json['on_road_nv'] = $onRoad;
        $json['on_road_ov'] = $onRoad;

        $cash = (float) $json['discounts']['cash'];
        $json['discounts']['cash_portion'] = $cash;
        $json['discounts']['credit_note'] = (float) $json['discounts']['oem_scheme']
            + (float) $json['discounts']['dealer_cont'];

        if (! $complete) {
            $json['errors'][] = 'Master incomplete: ' . implode(',', $this->vehicles->missingFields($variant));
        }

        return $json;
    }

    protected function persistSnapshot(
        array $payload,
        string $oemCode,
        string $channel,
        string $vinType,
        ?string $wefDate,
        ?int $sessionId,
        ?int $userId
    ): void {
        if (! Schema::hasTable('xlr8_vehicle_pricing_snapshots')) {
            Log::warning('[PricingEngine] snapshot table missing — JSON not persisted');
            return;
        }

        $wef = $wefDate ?? ($payload['wef_date'] ?? now()->toDateString());

        Snapshot::query()
            ->where('model_code', $oemCode)
            ->where('channel', $channel)
            ->where('vin_type', $vinType)
            ->where('is_active', true)
            ->whereDate('wef_date', '<', $wef)
            ->update([
                'is_active'  => false,
                'expired_on' => $wef,
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

        Snapshot::query()->updateOrCreate(
            [
                'model_code' => $oemCode,
                'channel'    => $channel,
                'vin_type'   => $vinType,
                'wef_date'   => $wef,
            ],
            [
                'import_session_id' => $sessionId,
                'variant_code'      => $oemCode,
                'payload'           => $payload,
                'is_active'         => true,
                'expired_on'        => null,
                'updated_by'        => $userId,
                'created_by'        => $userId,
            ]
        );
    }

    protected function dealerCharges(Variant $variant, ?string $permit): array
    {
        $out = PricingJsonContract::empty()['dealer_charges'];
        if (! Schema::hasTable('xlr8_vehicle_pricing_dealer_charges')) {
            return $out;
        }

        $rows = DB::table('xlr8_vehicle_pricing_dealer_charges')
            ->when(Schema::hasColumn('xlr8_vehicle_pricing_dealer_charges', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->when(Schema::hasColumn('xlr8_vehicle_pricing_dealer_charges', 'is_active'), fn ($q) => $q->where('is_active', 1))
            ->get();

        $picked = [];
        foreach ($rows as $row) {
            if (! $this->scopeHit($row, $variant, $permit)) {
                continue;
            }
            $name = strtolower((string) ($row->charge_name ?? $row->name ?? $row->head ?? 'other'));
            $amt = (float) ($row->amount ?? 0);
            $key = 'other';
            if (str_contains($name, 'incidental')) {
                $key = 'incidental';
            } elseif (str_contains($name, 'fast')) {
                $key = 'fasttag';
            } elseif (str_contains($name, 'trc')) {
                $key = 'trc';
            } elseif (str_contains($name, 'tape')) {
                $key = 'rto_tape';
            } elseif (str_contains($name, 'cod')) {
                $key = 'cod';
            }
            $picked[$key] = $amt;
            $out['lines'][] = ['name' => $name, 'amount' => $amt];
        }
        foreach ($picked as $k => $v) {
            $out[$k] = $v;
        }
        $out['total'] = round(array_sum($picked), 2);

        return $out;
    }

    protected function addonOptions(Variant $variant, string $type, ?int $defaultYears): array
    {
        $isRsa = strtoupper($type) === 'RSA';
        $out = $isRsa
            ? ['selected_years' => $defaultYears ?? 1, 'selected_amount' => 0.0, 'options' => []]
            : ['selected_scheme' => null, 'selected_amount' => 0.0, 'options' => []];

        if (! Schema::hasTable('xlr8_vehicle_pricing_addons')) {
            return $out;
        }

        $rows = DB::table('xlr8_vehicle_pricing_addons')
            ->where('addon_type', $type)
            ->when(Schema::hasColumn('xlr8_vehicle_pricing_addons', 'is_active'), fn ($q) => $q->where('is_active', 1))
            ->when(Schema::hasColumn('xlr8_vehicle_pricing_addons', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->get();

        $opts = [];
        foreach ($rows as $row) {
            if (! $this->scopeHit($row, $variant, null)) {
                continue;
            }
            $opts[] = [
                'scheme' => $row->scheme_name ?? $row->name ?? null,
                'years'  => $row->tenure_years ?? null,
                'amount' => (float) ($row->amount ?? 0),
                'default'=> (bool) ($row->is_default ?? false),
            ];
        }
        $out['options'] = $opts;
        $def = collect($opts)->firstWhere('default', true) ?? ($opts[0] ?? null);
        if ($def) {
            $out['selected_amount'] = (float) $def['amount'];
            if ($isRsa) {
                $out['selected_years'] = (int) ($def['years'] ?? $out['selected_years']);
            } else {
                $out['selected_scheme'] = $def['scheme'];
            }
        }

        return $out;
    }

    protected function discountOptions(Variant $variant, string $type): array
    {
        $base = strtoupper($type) === 'CORPORATE'
            ? ['category' => null, 'oem' => 0.0, 'dealer' => 0.0, 'total' => 0.0, 'options' => []]
            : ['scheme' => null, 'oem' => 0.0, 'dealer' => 0.0, 'total' => 0.0, 'options' => []];

        if (! Schema::hasTable('xlr8_vehicle_pricing_discounts')) {
            return $base;
        }

        $rows = DB::table('xlr8_vehicle_pricing_discounts')
            ->where('discount_type', $type)
            ->when(Schema::hasColumn('xlr8_vehicle_pricing_discounts', 'is_active'), fn ($q) => $q->where('is_active', 1))
            ->when(Schema::hasColumn('xlr8_vehicle_pricing_discounts', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->get();

        foreach ($rows as $row) {
            if (! $this->scopeHit($row, $variant, null)) {
                continue;
            }
            $oem = (float) ($row->oem_share ?? 0);
            $dlr = (float) ($row->dealer_share ?? 0);
            $base['options'][] = [
                'name'   => $row->scheme_name ?? $row->category ?? $row->name ?? null,
                'oem'    => $oem,
                'dealer' => $dlr,
                'total'  => round($oem + $dlr, 2),
            ];
        }

        return $base;
    }

    protected function scopeHit(object $row, Variant $variant, ?string $permit): bool
    {
        $map = [
            'segment' => $variant->segment_code,
            'model'   => $variant->model_code,
            'variant' => $variant->code,
            'permit'  => $permit,
        ];
        foreach ($map as $col => $val) {
            if (! isset($row->{$col})) {
                continue;
            }
            $rv = strtoupper(trim((string) $row->{$col}));
            if ($rv === '' || $rv === 'ANY' || $rv === 'ALL') {
                continue;
            }
            $act = strtoupper(trim((string) ($val ?? '')));
            if ($act !== '' && ! in_array($act, array_map('trim', explode(',', $rv)), true)) {
                return false;
            }
        }

        return true;
    }

    protected function isHeld(?string $segment): bool
    {
        if (! $segment || ! Schema::hasTable('xlr8_vehicle_pricing_holds')) {
            return false;
        }

        return DB::table('xlr8_vehicle_pricing_holds')
            ->where('is_active', 1)
            ->where(function ($q) use ($segment) {
                $q->where('scope', 'ALL')->orWhere('scope', $segment)->orWhere('segment', $segment);
            })
            ->when(Schema::hasColumn('xlr8_vehicle_pricing_holds', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->exists();
    }

    protected function kv(mixed $id): ?string
    {
        if (! $id) {
            return null;
        }
        try {
            foreach (['xlr8_utilities_keyvalues', 'xlr8_keyvalues'] as $table) {
                if (Schema::hasTable($table)) {
                    $v = DB::table($table)->where('id', $id)->value('value');
                    if ($v) {
                        return (string) $v;
                    }
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}

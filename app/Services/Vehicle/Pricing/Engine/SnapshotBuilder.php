<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Engine;

use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Variant;

/**
 * Builds a vehicle's published price snapshots (DEC-073 / DEC-080): one per permit (the vehicle's; a taxi-priced private
 * vehicle adds PASSENGER) × VIN type (NV = current schemes, OV = old-VIN schemes) × channel (normal; csd when a CSD
 * price exists). Insurance and RTO are computed once per permit and channel and shared by NV / OV.
 *
 *   on_road = ex-showroom + dealer charges + RSA + Shield + accessories + insurance + RTO + TCS − discounts
 *   discounts (default) = cash + accessory + Shield + RSA + consumer scheme (OEM scheme + dealer contribution)
 *   accessories (default) = the accessory discount; TCS = rate × (ex-showroom − discounts) when ex-showroom ≥ limit
 *
 * build() throws PricingFailure when no RTO or insurance rule matches (the vehicle is reported, nothing published).
 */
final class SnapshotBuilder
{
    private RtoCalculator $rto;

    private InsuranceCalculator $insurance;

    private ComponentResolver $components;

    public function __construct(private readonly RuleBook $book)
    {
        $this->rto = new RtoCalculator($book);
        $this->insurance = new InsuranceCalculator($book);
        $this->components = new ComponentResolver($book);
    }

    /**
     * @param  array<string, Pricing>  $prices  channel (normal / csd) => the live price
     * @return list<array{channel: string, vin_type: string, permit: string, rto_permit: string, insu_permit: string, payload: array<string, mixed>}>
     *
     * @throws PricingFailure
     */
    public function build(Variant $variant, array $prices, string $wefDate): array
    {
        $v = VehicleFacts::of($variant, $this->book->keyvalueCodes);
        if ($v->permit === null) {
            throw new PricingFailure('The vehicle has no permit.');
        }
        if (! isset($prices['normal'])) {
            throw new PricingFailure('No live price.');
        }
        $permits = [$v->permit];
        if ($v->taxi && $v->permit === 'PRIVATE') {
            $permits[] = 'PASSENGER';
        }
        $rsa = $this->components->rsa($v);
        $shield = $this->components->shield($v);
        $exchange = $this->components->discountOptions($v, 'EXCHANGE');
        $corporate = $this->components->discountOptions($v, 'CORPORATE');
        $loyalty = $this->components->discountOptions($v, 'LOYALTY');

        $out = [];
        foreach ($permits as $permit) {
            $map = $this->book->permits($permit, $v->wheels);
            $rtoPermit = $map->rto_permit ?? ucfirst(strtolower($permit));
            $insuPermit = $map->insu_permit ?? ucfirst(strtolower($permit));
            $charges = $this->components->dealerCharges($v, $permit);
            foreach ($prices as $channel => $price) {
                $ex = (float) $price->ex_showroom_price;
                $rto = $this->rto->calculate($v, $rtoPermit, $ex, (float) $price->assessable_value_with_freight, (float) $price->dealer_margin)
                    ?? throw new PricingFailure("No RTO rule matches ({$rtoPermit}, {$v->wheels}W, {$v->fuel}, body {$v->bodyType}).");
                $insurance = $this->insurance->calculate($v, $insuPermit, $ex, $v->modelCode)
                    ?? throw new PricingFailure("No insurance rule matches ({$insuPermit}, {$v->wheels}W, ".($v->isElectric() ? 'EV' : 'ICE').').');
                foreach (['NV', 'OV'] as $vin) {
                    $out[] = [
                        'channel' => $channel, 'vin_type' => $vin, 'permit' => $permit, 'rto_permit' => $rtoPermit, 'insu_permit' => $insuPermit,
                        'payload' => $this->payload($v, $price, $channel, $vin, $permit, $rtoPermit, $insuPermit, $wefDate, $charges, $rsa, $shield, $exchange, $corporate, $rto, $insurance, $loyalty),
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $charges
     * @param  array<string, mixed>  $rsa
     * @param  array<string, mixed>  $shield
     * @param  list<array<string, mixed>>  $exchange
     * @param  list<array<string, mixed>>  $corporate
     * @param  list<array<string, mixed>>  $loyalty
     * @param  array<string, mixed>  $rto
     * @param  array<string, mixed>  $insurance
     * @return array<string, mixed>
     */
    private function payload(VehicleFacts $v, Pricing $price, string $channel, string $vin, string $permit, string $rtoPermit, string $insuPermit, string $wef,
        array $charges, array $rsa, array $shield, array $exchange, array $corporate, array $rto, array $insurance, array $loyalty = []): array
    {
        $p = $vin === 'NV' ? 'curr_' : 'old_';
        $ex = (float) $price->ex_showroom_price;
        $oem = (float) $price->{$p.'oem_scheme'};
        $dealer = (float) $price->{$p.'dealer_cont'};
        $discounts = [
            'oem_scheme' => $oem, 'dealer_cont' => $dealer, 'consumer_scheme' => $oem + $dealer,
            'cash' => (float) $price->{$p.'cash_discount'}, 'accessory' => (float) $price->{$p.'acc_discount'},
            'shield' => (float) $price->{$p.'shield_discount'}, 'rsa' => 0.0,
            'accessory_eligibility' => (float) $price->{$p.'acc_elg'}, 'shield_eligibility' => (float) $price->{$p.'shield_elg'},
        ];
        $discounts['total'] = $discounts['consumer_scheme'] + $discounts['cash'] + $discounts['accessory'] + $discounts['shield'] + $discounts['rsa'];
        $discounts['exchange'] = ['selected' => null, 'amount' => 0.0, 'options' => $exchange];
        $discounts['corporate'] = ['selected' => null, 'amount' => 0.0, 'options' => $corporate];
        $discounts['loyalty'] = ['selected' => null, 'amount' => 0.0, 'options' => $loyalty];
        $accessories = ['amount' => $discounts['accessory'], 'discount' => $discounts['accessory'], 'items' => []];

        $limit = (float) $this->book->tcs->limit_amount;
        $rate = (float) $this->book->tcs->rate_pct;
        $tcsBase = max(0.0, $ex - $discounts['total']);
        $tcsApplies = $ex >= $limit && $limit > 0;
        $tcs = ['limit' => $limit, 'rate' => $rate, 'base' => $tcsBase, 'applicable' => $tcsApplies, 'amount' => $tcsApplies ? round($tcsBase * $rate / 100) : 0.0];

        $gross = $ex + $charges['total'] + $rsa['selected_amount'] + $shield['selected_amount'] + $accessories['amount'] + $insurance['default']['total'] + $rto['total'];
        $model = $v->variant->vehicleModel;

        return PricingContract::normalize([
            'oem_code' => $v->code, 'display_name' => $v->variant->display_name, 'custom_model' => $model?->name, 'custom_variant' => $v->variant->custom_name,
            'colour' => $v->variant->color, 'segment' => $v->segment, 'sub_segment' => $v->subSegment, 'model_code' => $v->modelCode,
            'price_list' => $price->price_list, 'vehicle_permit' => $v->permit, 'permit' => $permit, 'rto_permit' => $rtoPermit, 'insu_permit' => $insuPermit,
            'fuel' => $v->fuel, 'taxi_price' => $v->taxi ? 'YES' : 'NO', 'channel' => $channel, 'vin_type' => $vin, 'wef_date' => $wef,
            'ex_showroom' => $ex, 'assessable_value' => (float) $price->assessable_value_with_freight, 'gst_percent' => (float) $price->gst_percent,
            'gst_amount' => (float) $price->gst_amount, 'mm_invoice' => (float) $price->mm_invoice_amount, 'dealer_margin' => (float) $price->dealer_margin,
            'dealer_charges' => $charges, 'rsa' => $rsa, 'shield' => $shield, 'accessories' => $accessories, 'discounts' => $discounts,
            'insurance' => $insurance, 'rto' => $rto, 'tcs' => $tcs,
            'gross' => $gross, 'invoice_value' => $ex - $discounts['total'] + $tcs['amount'],
            'on_road' => $gross + $tcs['amount'] - $discounts['total'],
        ]);
    }
}

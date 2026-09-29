<?php

declare(strict_types=1);

namespace App\Support\PricingMaster\Masters;

use App\Models\Vehicle\Pricing\Pricing;
use App\Services\Vehicle\Pricing\Prices\PriceService;
use App\Support\Entity\EntityService;
use App\Support\PricingMaster\MasterDefinition;

/**
 * Discounting Breakup (DEC-083): the per-vehicle scheme blocks of the live price rows — OEM scheme, dealer
 * contribution, cash, accessory and Shield discounts and the eligibility ratios, NV (`curr_*`) and OV (`old_*`).
 * Same WEF edits the price row; a new WEF versions it (the rest of the price carries forward). One row per record export.
 */
final class DiscountBreakupMaster extends MasterDefinition
{
    private const SCHEMES = [
        'curr_oem_scheme' => 'NV OEM Scheme', 'curr_dealer_cont' => 'NV Dealer Cont.', 'curr_cash_discount' => 'NV Cash',
        'curr_acc_discount' => 'NV Accessory', 'curr_shield_discount' => 'NV Shield', 'curr_acc_elg' => 'NV Acc. Eligibility', 'curr_shield_elg' => 'NV Shield Eligibility',
        'old_oem_scheme' => 'OV OEM Scheme', 'old_dealer_cont' => 'OV Dealer Cont.', 'old_cash_discount' => 'OV Cash',
        'old_acc_discount' => 'OV Accessory', 'old_shield_discount' => 'OV Shield', 'old_acc_elg' => 'OV Acc. Eligibility', 'old_shield_elg' => 'OV Shield Eligibility',
    ];

    public function key(): string
    {
        return 'discount-breakup';
    }

    public function label(): string
    {
        return 'Discounting Breakup';
    }

    public function permission(): string
    {
        return 'PRC_DBRK';
    }

    public function icon(): string
    {
        return 'la-percentage';
    }

    public function description(): string
    {
        return 'Per-vehicle scheme blocks of the live price: NV (current VIN) and OV (old VIN). Prices themselves come from the Pricing Process.';
    }

    public function model(): string
    {
        return Pricing::class;
    }

    public function service(): EntityService
    {
        return app(PriceService::class);
    }

    public function formFields(): array
    {
        return array_merge(['model_code', 'channel', 'price_list', 'wef_date', 'ex_showroom_price'], array_keys(self::SCHEMES));
    }

    public function columns(): array
    {
        $columns = [
            ['field' => 'model_code', 'label' => 'OEM Code', 'pinned' => 'left', 'width' => 190],
            ['field' => 'channel', 'label' => 'Channel', 'width' => 100],
            ['field' => 'price_list', 'label' => 'Price List', 'width' => 110],
            ['field' => 'ex_showroom_price', 'label' => 'Ex-showroom', 'type' => 'number', 'width' => 130],
        ];
        foreach (self::SCHEMES as $field => $label) {
            $columns[] = ['field' => $field, 'label' => $label, 'type' => 'number', 'width' => 130];
        }
        $columns[] = ['field' => 'wef_date', 'label' => 'WEF', 'type' => 'date', 'width' => 120];

        return $columns;
    }
}

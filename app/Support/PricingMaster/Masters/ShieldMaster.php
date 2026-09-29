<?php

declare(strict_types=1);

namespace App\Support\PricingMaster\Masters;

/** Shield (extended warranty) schemes by model / variant / pack / transmission / fuel (DEC-083). */
final class ShieldMaster extends AddonMaster
{
    public function key(): string
    {
        return 'shield';
    }

    public function label(): string
    {
        return 'Shield';
    }

    public function permission(): string
    {
        return 'PRC_SHLD';
    }

    public function icon(): string
    {
        return 'la-shield-alt';
    }

    public function group(): string
    {
        return 'SHIELD';
    }

    public function formFields(): array
    {
        return ['model_code', 'variant_code', 'shield_pack', 'transmission', 'fuel', 'name', 'scheme_name', 'tenure_years', 'amount', 'is_default', 'wef_date'];
    }

    public function columns(): array
    {
        return [
            ['field' => 'model_code', 'label' => 'Model', 'pinned' => 'left', 'width' => 150],
            ['field' => 'variant_code', 'label' => 'Variant', 'width' => 170],
            ['field' => 'shield_pack', 'label' => 'Shield Pack', 'width' => 130],
            ['field' => 'transmission', 'label' => 'Transmission', 'width' => 130],
            ['field' => 'fuel', 'label' => 'Fuel', 'width' => 100],
            ['field' => 'name', 'label' => 'Standard Warranty', 'width' => 170],
            ['field' => 'scheme_name', 'label' => 'Scheme', 'width' => 170],
            ['field' => 'tenure_years', 'label' => 'Scheme No.', 'type' => 'number', 'width' => 110],
            ['field' => 'amount', 'label' => 'Amount', 'type' => 'number'],
            ['field' => 'wef_date', 'label' => 'WEF', 'type' => 'date', 'width' => 120],
        ];
    }
}

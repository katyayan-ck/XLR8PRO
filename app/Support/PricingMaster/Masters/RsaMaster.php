<?php

declare(strict_types=1);

namespace App\Support\PricingMaster\Masters;

/** RSA by segment / model and tenure (DEC-083). Default = the first paid 1-year option. */
final class RsaMaster extends AddonMaster
{
    public function key(): string
    {
        return 'rsa';
    }

    public function label(): string
    {
        return 'RSA';
    }

    public function permission(): string
    {
        return 'PRC_RSA';
    }

    public function icon(): string
    {
        return 'la-road';
    }

    public function group(): string
    {
        return 'RSA';
    }

    public function formFields(): array
    {
        return ['segment', 'model_code', 'name', 'scheme_name', 'tenure_years', 'amount', 'is_default', 'wef_date'];
    }

    public function columns(): array
    {
        return [
            ['field' => 'segment', 'label' => 'Segment', 'pinned' => 'left', 'width' => 110],
            ['field' => 'model_code', 'label' => 'Model', 'width' => 150],
            ['field' => 'name', 'label' => 'Standard Coverage', 'width' => 170],
            ['field' => 'scheme_name', 'label' => 'Scheme', 'width' => 150],
            ['field' => 'tenure_years', 'label' => 'Years', 'type' => 'number', 'width' => 90],
            ['field' => 'amount', 'label' => 'Amount', 'type' => 'number'],
            ['field' => 'is_default', 'label' => 'Default', 'type' => 'bool', 'width' => 100],
            ['field' => 'wef_date', 'label' => 'WEF', 'type' => 'date', 'width' => 120],
        ];
    }
}

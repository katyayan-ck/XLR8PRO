<?php

declare(strict_types=1);

namespace App\Support\PricingMaster\Masters;

use App\Models\Vehicle\Pricing\Discount;
use App\Services\Vehicle\Pricing\Addons\DiscountService;
use App\Support\Entity\EntityService;
use App\Support\PricingMaster\WorkbookGroupMaster;
use Illuminate\Database\Eloquent\Model;

/**
 * Exchange / Loyalty (by scheme) and Corporate (by category) conditional discounts: OEM share + dealer share = total, per
 * model / variant (DEC-083). Offered on the quotation and the Price List, never in the default on-road.
 */
abstract class ConditionalDiscountMaster extends WorkbookGroupMaster
{
    public function model(): string
    {
        return Discount::class;
    }

    public function service(): EntityService
    {
        return app(DiscountService::class);
    }

    public function defaults(): array
    {
        return ['discount_type' => $this->group()];
    }

    /** scheme_name (Exchange, Loyalty) or category (Corporate). */
    protected function optionField(): string
    {
        return $this->group() === 'CORPORATE' ? 'category' : 'scheme_name';
    }

    public function formFields(): array
    {
        return ['model_code', 'variant_code', $this->optionField(), 'oem_share', 'dealer_share', 'total_discount', 'wef_date'];
    }

    public function columns(): array
    {
        return [
            ['field' => 'model_code', 'label' => 'Model', 'pinned' => 'left', 'width' => 150],
            ['field' => 'variant_code', 'label' => 'Variant', 'width' => 170],
            ['field' => $this->optionField(), 'label' => $this->group() === 'CORPORATE' ? 'Category' : 'Scheme', 'width' => 160],
            ['field' => 'oem_share', 'label' => 'OEM Share', 'type' => 'number'],
            ['field' => 'dealer_share', 'label' => 'Dealer Share', 'type' => 'number'],
            ['field' => 'total_discount', 'label' => 'Total', 'type' => 'number'],
            ['field' => 'wef_date', 'label' => 'WEF', 'type' => 'date', 'width' => 120],
        ];
    }

    public function save(array $input, ?Model $existing = null): Model
    {
        $option = trim((string) ($input[$this->optionField()] ?? $existing?->getAttribute($this->optionField()) ?? ''));
        $input += ['name' => $option !== '' ? $option : ucfirst(strtolower($this->group())), 'discount_category' => $this->group() === 'CORPORATE' ? $option : $this->group(), 'is_conditional' => true];

        return parent::save($input, $existing);
    }
}

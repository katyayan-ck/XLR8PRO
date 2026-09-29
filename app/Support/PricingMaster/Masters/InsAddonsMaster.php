<?php

declare(strict_types=1);

namespace App\Support\PricingMaster\Masters;

use App\Models\Vehicle\Pricing\InsAddon;
use App\Services\Vehicle\Pricing\Rules\InsAddonService;
use App\Support\Entity\EntityService;
use App\Support\PricingMaster\MasterDefinition;
use Illuminate\Database\Eloquent\Builder;

/**
 * Insurance add-on master (DEC-083): codes, names and the default (frozen) combo; rates stay in Insurance Rules.
 *
 * @extends MasterDefinition<InsAddon>
 */
final class InsAddonsMaster extends MasterDefinition
{
    public function key(): string
    {
        return 'insurance-addons';
    }

    public function label(): string
    {
        return 'Insurance Add-ons';
    }

    public function permission(): string
    {
        return 'PRC_INAD';
    }

    public function icon(): string
    {
        return 'la-puzzle-piece';
    }

    public function description(): string
    {
        return 'Add-ons marked "In the default combo" are included in the default insurance of every snapshot (NilDep + Consumables to start).';
    }

    public function model(): string
    {
        return InsAddon::class;
    }

    public function service(): EntityService
    {
        return app(InsAddonService::class);
    }

    public function formFields(): array
    {
        return ['code', 'name', 'is_default', 'sort_order', 'is_active'];
    }

    /** @return Builder<InsAddon> */
    public function query(bool $history = false): Builder
    {
        return InsAddon::query()->when(! $history, fn ($q) => $q->where('is_active', true))->orderBy('sort_order');
    }

    public function columns(): array
    {
        return [
            ['field' => 'code', 'label' => 'Code', 'pinned' => 'left', 'width' => 150],
            ['field' => 'name', 'label' => 'Name', 'width' => 320],
            ['field' => 'is_default', 'label' => 'In the default combo', 'type' => 'bool', 'width' => 170],
            ['field' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'width' => 100],
            ['field' => 'is_active', 'label' => 'Active', 'type' => 'bool', 'width' => 100],
        ];
    }
}

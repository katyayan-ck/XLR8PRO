<?php

declare(strict_types=1);

namespace App\Support\PricingMaster\Masters;

use App\Models\Vehicle\Pricing\InsCompany;
use App\Services\Vehicle\Pricing\Rules\InsCompanyService;
use App\Support\Entity\EntityService;
use App\Support\PricingMaster\MasterDefinition;
use Illuminate\Database\Eloquent\Builder;

/**
 * Insurance company master (DEC-083). The code is what rules and preferences store.
 *
 * @extends MasterDefinition<InsCompany>
 */
final class InsCompaniesMaster extends MasterDefinition
{
    public function key(): string
    {
        return 'insurance-companies';
    }

    public function label(): string
    {
        return 'Insurance Companies';
    }

    public function permission(): string
    {
        return 'PRC_INCO';
    }

    public function icon(): string
    {
        return 'la-building';
    }

    public function model(): string
    {
        return InsCompany::class;
    }

    public function service(): EntityService
    {
        return app(InsCompanyService::class);
    }

    public function formFields(): array
    {
        return ['code', 'name', 'short_name', 'sort_order', 'is_active'];
    }

    /** @return Builder<InsCompany> */
    public function query(bool $history = false): Builder
    {
        return InsCompany::query()->when(! $history, fn ($q) => $q->where('is_active', true))->orderBy('sort_order');
    }

    public function columns(): array
    {
        return [
            ['field' => 'code', 'label' => 'Code', 'pinned' => 'left', 'width' => 130],
            ['field' => 'name', 'label' => 'Name', 'width' => 260],
            ['field' => 'short_name', 'label' => 'Short Name', 'width' => 150],
            ['field' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'width' => 100],
            ['field' => 'is_active', 'label' => 'Active', 'type' => 'bool', 'width' => 100],
        ];
    }
}

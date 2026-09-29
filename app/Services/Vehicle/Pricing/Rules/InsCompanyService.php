<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Rules;

use App\Models\Vehicle\Pricing\InsCompany;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;

/**
 * Insurance company master (xlr8_vehicle_pricing_ins_companies) — its only write path (DEC-050 / DEC-083).
 *
 * @extends EntityService<InsCompany>
 */
final class InsCompanyService extends EntityService
{
    protected function model(): string
    {
        return InsCompany::class;
    }

    public function fields(): array
    {
        return [
            Field::code('code', 40)->label('Code')->format('Upper case, as the insurance rules write it (e.g. USGI)')->required()->unique()->immutable(),
            Field::name('name', 120)->label('Name')->required(),
            Field::text('short_name', 40)->label('Short Name'),
            Field::integer('sort_order')->label('Order')->default(0),
            Field::flag('is_active', true)->label('Active'),
        ];
    }
}

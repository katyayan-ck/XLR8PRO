<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use App\Services\Vehicle\Pricing\Rules\InsCompanyService;

/**
 * Insurance company master (DEC-083). `code` is what insurance rules and preferences store as the company.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $short_name
 * @property int $sort_order
 * @property bool $is_active
 */
class InsCompany extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_ins_companies';

    protected string $entityService = InsCompanyService::class;

    protected $fillable = ['code', 'name', 'short_name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['is_active' => 'boolean', 'sort_order' => 'integer']);
    }
}

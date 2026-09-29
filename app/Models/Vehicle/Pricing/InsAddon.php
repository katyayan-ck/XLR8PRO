<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use App\Services\Vehicle\Pricing\Rules\InsAddonService;

/**
 * Insurance add-on master (DEC-083): the add-on codes the Insurance Rules rate, their display names and whether they are
 * part of the default (frozen) insurance combo. Rates / premiums stay per rule (InsAddonRate).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_default
 * @property int $sort_order
 * @property bool $is_active
 */
class InsAddon extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_ins_addons';

    protected string $entityService = InsAddonService::class;

    protected $fillable = ['code', 'name', 'is_default', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['is_active' => 'boolean', 'is_default' => 'boolean', 'sort_order' => 'integer']);
    }

    /** @return list<string> codes of the default combo (NIL_DEP, CONSUMABLES unless changed) */
    public static function defaultCodes(): array
    {
        return self::query()->where('is_active', true)->where('is_default', true)->orderBy('sort_order')->pluck('code')
            ->map(fn ($c) => strtoupper((string) $c))->values()->all();
    }
}

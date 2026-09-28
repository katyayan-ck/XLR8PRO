<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per add-on row imported (DEC-077): the parsed sheet row and what the import did. Engine-written; the table's
 * columns (BUG-201).
 *
 * @property int $id
 * @property int|null $addon_id
 * @property string|null $model_code
 * @property string|null $addon_type
 * @property array<string, mixed>|null $payload
 * @property string|null $action
 */
class AddonHistory extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_addon_history';

    protected $fillable = ['addon_id', 'model_code', 'addon_type', 'payload', 'action'];

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['payload' => 'array']);
    }

    /** @return BelongsTo<Addon, $this> */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class, 'addon_id');
    }
}

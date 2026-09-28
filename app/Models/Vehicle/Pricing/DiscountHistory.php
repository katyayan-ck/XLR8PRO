<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per discount row imported (DEC-077): the parsed sheet row and what the import did. Engine-written; the table's
 * columns (BUG-201).
 *
 * @property int $id
 * @property int|null $discount_id
 * @property string|null $model_code
 * @property array<string, mixed>|null $payload
 * @property string|null $action
 */
class DiscountHistory extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_discount_history';

    protected $fillable = ['discount_id', 'model_code', 'payload', 'action'];

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['payload' => 'array']);
    }

    /** @return BelongsTo<Discount, $this> */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class, 'discount_id');
    }
}

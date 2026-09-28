<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per price-list row imported (DEC-073 / DEC-076): what the sheet said and what the import did with it
 * (`insert` / `update` / `unchanged`). Engine-written; the table's columns (BUG-201).
 *
 * @property int $id
 * @property int|null $pricing_id
 * @property string $model_code
 * @property string $channel
 * @property Carbon|null $wef_date
 * @property array<string, mixed>|null $payload
 * @property string $action
 */
class PricingHistory extends BaseModel
{
    public const ACTION_INSERT = 'insert';

    public const ACTION_UPDATE = 'update';

    public const ACTION_UNCHANGED = 'unchanged';

    protected $table = 'xlr8_vehicle_pricing_history';

    protected $fillable = [
        'pricing_id',
        'model_code',
        'channel',
        'wef_date',
        'payload',
        'action',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'wef_date' => 'date',
            'payload' => 'array',
        ]);
    }

    /** @return BelongsTo<Pricing, $this> */
    public function pricing(): BelongsTo
    {
        return $this->belongsTo(Pricing::class, 'pricing_id');
    }
}

<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsIdvSlot extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_ins_idv_slots';

    protected $fillable = [
        'base_rule_id',
        'year_no',
        'idv_basis',
        'idv_pct',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'year_no' => 'integer',
            'idv_pct' => 'decimal:3',
        ]);
    }

    public function baseRule(): BelongsTo
    {
        return $this->belongsTo(InsBaseRule::class, 'base_rule_id', 'id');
    }
}

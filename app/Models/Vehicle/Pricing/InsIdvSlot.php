<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use App\Services\Vehicle\Pricing\Rules\InsIdvSlotService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsIdvSlot extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_ins_idv_slots';

    /** Columns = the entity service's fields (DEC-050/056); the service owns their rules. */
    protected string $entityService = InsIdvSlotService::class;

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

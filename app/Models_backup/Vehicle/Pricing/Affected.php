<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class Affected extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_affected';

    protected $fillable = [
        'import_session_id',
        'model_code',
        'variant_code',
        'status',
        'error_message',
    ];

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeForSession(Builder $query, int $sessionId): Builder
    {
        return $query->where('import_session_id', $sessionId);
    }
}
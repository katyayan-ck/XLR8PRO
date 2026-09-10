<?php

namespace App\Models;

use App\Models\Traits\ScopedQuery;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class XlSpareRequest extends BaseModel
{
    use SoftDeletes, ScopedQuery;

    protected $table = 'xlr8_spare_request';

    protected $guarded = ['id'];

    /**
     * DataScopeFilter config — filters by branch_code column.
     * If this table uses an integer branch_id instead, change scopeColumn to 'branch_id'
     * and keep it commented until branch column migration is done (same as Booking/Stock).
     */
    public string $scopeType   = 'branch';
    public string $scopeColumn = 'branch_code'; // ← change to 'branch_id' if needed
    public string $scopeGroup  = 'org';

    // ── Relations ─────────────────────────────────────────────────────

    public function details(): HasMany
    {
        return $this->hasMany(XlSpareRequestDetail::class, 'spare_req_id');
    }
}

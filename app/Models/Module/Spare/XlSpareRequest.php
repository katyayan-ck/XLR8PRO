<?php

namespace App\Models\Module\Spare;

use App\Models\BaseModel;
use App\Models\Traits\ScopedQuery;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class XlSpareRequest extends BaseModel
{
    use ScopedQuery, SoftDeletes;

    protected $table = 'xlr8_spare_request';

    protected $guarded = ['id'];

    /**
     * DataScopeFilter config. The table's branch column is srv_brnch_id
     * (an id; there is no branch_code column).
     */
    public string $scopeType = 'branch';

    public string $scopeColumn = 'srv_brnch_id';

    public string $scopeGroup = 'org';

    // ── Relations ─────────────────────────────────────────────────────

    public function details(): HasMany
    {
        return $this->hasMany(XlSpareRequestDetail::class, 'spare_req_id');
    }
}

<?php

namespace App\Models\Module\Spare;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class XlSpareRequest extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_spare_request';

    protected $guarded = ['id'];

    // Not data-scoped (DEC-071): the branch column srv_brnch_id holds an id, not a code; the Spares module is
    // hidden until its rebuild (D28), which should store a branch_code and list the model in config/data_scope.php.

    // ── Relations ─────────────────────────────────────────────────────

    public function details(): HasMany
    {
        return $this->hasMany(XlSpareRequestDetail::class, 'spare_req_id');
    }
}

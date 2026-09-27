<?php

namespace App\Models\Module\Insurance;

use App\Models\BaseModel;
use App\Models\Traits\HasDataScope;
use App\Models\Traits\HasDocuments;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class XlInsurance extends BaseModel
{
    use HasDataScope;   // DEC-071: filtered by the signed-in user's data scope (config/data_scope.php)
    use HasDocuments;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    use SoftDeletes;

    /** Proof files live in Docs (DEC-069) and follow booking access: SLS_BKNG_VIEW. */
    public function chatCanView(int $userId): bool
    {
        $user = User::query()->find($userId);

        return $user !== null && ($user->can('SLS_BKNG_VIEW'));
    }

    protected $table = 'xlr8_booking_insurance';

    /**
     * The attributes to be fillable from the model.
     *
     * A dirty hack to allow fields to be fillable by calling empty fillable array
     *
     * @var array
     */
    protected $fillable = [];

    protected $guarded = ['id'];
    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
}

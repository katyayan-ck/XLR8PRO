<?php

namespace App\Models\Module\Booking;

use App\Models\BaseModel;
use App\Models\Traits\ScopedQuery;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stock extends BaseModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    use SoftDeletes;

    protected $table = 'xlr8_booking_stock_master';

    // DataScopeFilter config. Not active: ScopedQuery is imported above but
    // not applied in the class body. The table has no branch column — stock
    // is scoped by location_id.
    public string $scopeType = 'location';

    public string $scopeColumn = 'location_id';

    public string $scopeGroup = 'org';

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

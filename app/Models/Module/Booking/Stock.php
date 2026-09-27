<?php

namespace App\Models\Module\Booking;

use App\Models\BaseModel;
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

    // Not data-scoped yet (DEC-071): location_id holds legacy ids (1, 2, 5) whose mapping to location codes is
    // unverified; add a location_code, then list the model in config/data_scope.php.

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

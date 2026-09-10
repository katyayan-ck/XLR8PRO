<?php

namespace App\Models\Module\Booking;

use Illuminate\Database\Eloquent\SoftDeletes;
use DataTables, Auth;
use App\Models\BaseModel;
class X_Vh_Order extends BaseModel
{
	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	use SoftDeletes;
	protected $table = 'xlr8_booking_order_master';

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

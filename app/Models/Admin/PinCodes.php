<?php
namespace App\Models\Admin;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\HasColumnTransformations;
use App\Models\BaseModel;


class PinCodes extends BaseModel
{
	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */

	protected $table = 'bmpl_pincodes';

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

	public function parentLocation()
{
    return $this->belongsTo(PinCodes::class, 'parent', 'id');
}

public function childLocations()
{
    return $this->hasMany(PinCodes::class, 'parent', 'id');
}

}

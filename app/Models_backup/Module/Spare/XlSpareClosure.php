<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use DataTables, Auth;

class XlSpareClosure extends BaseModel
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    use SoftDeletes;
    protected $table = 'xlr8_spare_closure';

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

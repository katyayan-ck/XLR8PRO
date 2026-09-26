<?php

namespace App\Models\Module\Spare;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

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

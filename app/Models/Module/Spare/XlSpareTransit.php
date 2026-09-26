<?php

namespace App\Models\Module\Spare;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class XlSpareTransit extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_spare_transit';

    protected $fillable = [];

    protected $guarded = ['id'];
}

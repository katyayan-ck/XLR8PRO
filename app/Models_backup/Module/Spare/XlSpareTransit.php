<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class XlSpareTransit extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_spare_transit';

    protected $fillable = [];
    protected $guarded = ['id'];
}

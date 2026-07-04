<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class XlSpareOrder extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_spare_order';

    protected $fillable = [];
    protected $guarded = ['id'];

    public function scopeForPartInStore($query, $partIds, $storeId)
    {
        return $query->whereIn('part_id', $partIds)
            ->where('store_id', $storeId);
    }
}

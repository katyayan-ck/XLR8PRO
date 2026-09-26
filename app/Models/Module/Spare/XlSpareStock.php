<?php

namespace App\Models\Module\Spare;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class XlSpareStock extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_spare_stock';

    protected $fillable = [];

    protected $guarded = ['id'];

    public function scopeForPartsInStores($query, $partIds, $storeIds)
    {
        return $query->whereIn('part_id', $partIds)
            ->whereIn('store_id', $storeIds);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class XlSpareConsumed extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_spare_consumption';

    protected $fillable = [];
    protected $guarded = ['id'];

    public function partMaster()
    {
        return $this->belongsTo(XlSpareMaster::class, 'part_id');
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('doc_date', [$startDate, $endDate]);
    }

    public function scopeForStores($query, $storeIds)
    {
        return $query->whereIn('store_id', $storeIds);
    }

    public function scopeFilterBySearch($query, $searchValue)
    {
        if ($searchValue) {
            $query->whereHas('partMaster', function ($q) use ($searchValue) {
                $q->where('part_no', 'like', '%' . $searchValue . '%')
                    ->orWhere('name', 'like', '%' . $searchValue . '%');
            });
        }
        return $query;
    }

    public function scopeFilterByCategory($query, $partCategory)
    {
        if ($partCategory) {
            $query->whereHas('partMaster', function ($q) use ($partCategory) {
                $q->where('category_id', $partCategory);
            });
        }
        return $query;
    }

    public function scopeFilterByDivision($query, $productDivision)
    {
        if ($productDivision) {
            $query->whereHas('partMaster', function ($q) use ($productDivision) {
                $q->where('division_id', $productDivision);
            });
        }
        return $query;
    }
}

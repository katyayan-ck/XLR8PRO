<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class XlSpareRequestDetail extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_spare_req_details';

    protected $fillable = [];
    protected $guarded = ['id'];

    public function spareRequest()
    {
        return $this->belongsTo(XlSpareRequest::class, 'spare_req_id');
    }

    public function partMaster()
    {
        return $this->belongsTo(XlSpareMaster::class, 'part_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at')->where('status', '!=', 2);
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

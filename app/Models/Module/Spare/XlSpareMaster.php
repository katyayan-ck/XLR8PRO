<?php

namespace App\Models;

class XlSpareMaster extends BaseModel
{
    protected $table = 'xlr8_spare_master';

    protected $fillable = [
        'part_no',
        'name',
        'category_id',
        'division_id',
        'order_price',
        'sale_price',
        'order_qty',
        // add relevant columns here
    ];

    public function category()
    {
        return $this->belongsTo(EnumMaster::class, 'category_id');
    }

    public function division()
    {
        return $this->belongsTo(EnumMaster::class, 'division_id');
    }

    public function stocks()
    {
        return $this->hasMany(XlSpareStock::class, 'part_id');
    }

    public function orders()
    {
        return $this->hasMany(XlSpareOrder::class, 'part_id');
    }

    public function consumptions()
    {
        return $this->hasMany(XlSpareConsumed::class, 'part_id');
    }

    public function scopeFilterBySearch($query, $searchValue)
    {
        if ($searchValue) {
            $query->where('part_no', 'like', '%' . $searchValue . '%')
                ->orWhere('name', 'like', '%' . $searchValue . '%');
        }
        return $query;
    }

    public function scopeFilterByCategory($query, $partCategory)
    {
        if ($partCategory) $query->where('category_id', $partCategory);
        return $query;
    }

    public function scopeFilterByDivision($query, $productDivision)
    {
        if ($productDivision) $query->where('division_id', $productDivision);
        return $query;
    }
}

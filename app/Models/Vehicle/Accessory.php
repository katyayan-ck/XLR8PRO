<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Accessory extends BaseModel
{
    protected $table = 'xlr8_vehicle_accessories';

    protected $fillable = [
        'part_no', 'display_name', 'item', 'ndp', 'mrp', 'details',
        'bundle', 'status', 'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'ndp' => 'decimal:2',
        'mrp' => 'decimal:2',
        'bundle' => 'boolean',
        'status' => 'integer',
    ];

    public function scopes(): HasMany
    {
        return $this->hasMany(\App\Models\Vehicle\AccessoryScope::class, 'part_no', 'part_no');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1)->whereNull('deleted_at');
    }

    public static function getAccessories(?string $segment = null, ?string $model = null, ?string $variant = null): \Illuminate\Support\Collection
{
    return self::where('status', 1)
        ->whereHas('scopes', function ($q) use ($segment, $model, $variant) {
            $q->where('status', 1)
              ->where(function ($sq) use ($segment) {
                  $sq->where('segment_code', $segment)->orWhereNull('segment_code');
              })
              ->when($model, fn($sq) => $sq->where(function ($sq2) use ($model) {
                  $sq2->where('model_code', $model)->orWhereNull('model_code');
              }))
              ->when($variant, fn($sq) => $sq->where(function ($sq2) use ($variant) {
                  $sq2->where('variant_code', $variant)->orWhereNull('variant_code');
              }));
        })
        ->with('scopes')
        ->get(['part_no', 'item', 'display_name', 'ndp', 'mrp']);
}

}

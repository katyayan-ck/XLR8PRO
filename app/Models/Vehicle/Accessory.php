<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Accessory extends BaseModel
{
    protected $table = 'xlr8_vehicle_accessories';

    public const TYPE_ACCESSORY = 'Accessory';
    public const TYPE_CERAMIC   = 'Ceramic';
    public const TYPE_PPF       = 'PPF';
    public const TYPE_MAXICARE  = 'Maxicare';
    public const TYPE_GPS_VLTD  = 'GPS_VLTD';
    public const TYPE_RTO_TAPE  = 'RTO_Tape';
    public const TYPE_KAZAM     = 'Kazam';

    /** Types included when caller asks for "all" catalog (excludes RTO_Tape + Kazam). */
    public const BUNDLE_TYPES = [
        self::TYPE_ACCESSORY,
        self::TYPE_CERAMIC,
        self::TYPE_PPF,
        self::TYPE_MAXICARE,
        self::TYPE_GPS_VLTD,
    ];

    public const ALL_TYPES = [
        self::TYPE_ACCESSORY,
        self::TYPE_CERAMIC,
        self::TYPE_PPF,
        self::TYPE_MAXICARE,
        self::TYPE_GPS_VLTD,
        self::TYPE_RTO_TAPE,
        self::TYPE_KAZAM,
    ];

    protected $fillable = [
        'part_no',
        'type',
        'display_name',
        'item',
        'ndp',
        'mrp',
        'set_qty',
        'discount',
        'details',
        'bundle',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'ndp'      => 'decimal:2',
        'mrp'      => 'decimal:2',
        'discount' => 'decimal:4',
        'set_qty'  => 'integer',
        'bundle'   => 'boolean',
        'status'   => 'integer',
    ];

    public function scopes(): HasMany
    {
        return $this->hasMany(AccessoryScope::class, 'part_no', 'part_no');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1)->whereNull('deleted_at');
    }

    public function scopeOfType(Builder $query, string|array $type): Builder
    {
        $types = is_array($type) ? $type : [$type];
        return $query->whereIn('type', $types);
    }
}

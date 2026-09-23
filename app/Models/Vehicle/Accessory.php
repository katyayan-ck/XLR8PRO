<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Accessory extends BaseModel
{
    protected $table = 'xlr8_vehicle_accessories';

    /** xlr8_vehicle_accessories.type enum, per the column's own DB comment. */
    public const TYPE_ACCESSORY = 'Accessory';

    public const TYPE_CERAMIC = 'Ceramic';

    public const TYPE_PPF = 'PPF';

    public const TYPE_MAXICARE = 'Maxicare';

    public const TYPE_GPS_VLTD = 'GPS_VLTD';

    public const TYPE_RTO_TAPE = 'RTO_Tape';

    public const TYPE_KAZAM = 'Kazam';

    /** Every catalog type. */
    public const ALL_TYPES = [
        self::TYPE_ACCESSORY,
        self::TYPE_CERAMIC,
        self::TYPE_PPF,
        self::TYPE_MAXICARE,
        self::TYPE_GPS_VLTD,
        self::TYPE_RTO_TAPE,
        self::TYPE_KAZAM,
    ];

    /**
     * The default "all"/"bundle" type set. Excludes RTO_Tape/Kazam, which
     * AccessoryService::listByType() docs as fetched explicitly, not as part
     * of the default bundle listing.
     */
    public const BUNDLE_TYPES = [
        self::TYPE_ACCESSORY,
        self::TYPE_CERAMIC,
        self::TYPE_PPF,
        self::TYPE_MAXICARE,
        self::TYPE_GPS_VLTD,
    ];

    protected $fillable = [
        'part_no', 'type', 'display_name', 'item', 'ndp', 'mrp', 'set_qty',
        'discount', 'details', 'bundle', 'status', 'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'ndp' => 'decimal:2',
        'mrp' => 'decimal:2',
        'set_qty' => 'integer',
        'discount' => 'decimal:2',
        'bundle' => 'boolean',
        'status' => 'integer',
    ];

    public function scopes(): HasMany
    {
        return $this->hasMany(AccessoryScope::class, 'part_no', 'part_no');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1)->whereNull('deleted_at');
    }

    public static function getAccessories(?string $segment = null, ?string $model = null, ?string $variant = null): Collection
    {
        return self::where('status', 1)
            ->whereHas('scopes', function ($q) use ($segment, $model, $variant) {
                $q->where('status', 1)
                    ->where(function ($sq) use ($segment) {
                        $sq->where('segment_code', $segment)->orWhereNull('segment_code');
                    })
                    ->when($model, fn ($sq) => $sq->where(function ($sq2) use ($model) {
                        $sq2->where('model_code', $model)->orWhereNull('model_code');
                    }))
                    ->when($variant, fn ($sq) => $sq->where(function ($sq2) use ($variant) {
                        $sq2->where('variant_code', $variant)->orWhereNull('variant_code');
                    }));
            })
            ->with('scopes')
            ->get(['part_no', 'item', 'display_name', 'ndp', 'mrp']);
    }
}

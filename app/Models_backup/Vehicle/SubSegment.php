<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use App\Models\Traits\HasColumnTransformations;

class SubSegment extends BaseModel
{
    use CrudTrait;
    use HasColumnTransformations;

    protected $table = 'xlr8_vehicle_subsegment';

    /**
     * Live DB: segment_code, code, name, is_active, audit.
     * NO oem_name — use name.
     */
    protected $fillable = [
        'segment_code',
        'code',
        'name',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected array $columnTransformations = [
        'segment_code' => ['trim', 'uppercase_alphanumeric_dash_underscore'],
        'code'         => ['trim', 'uppercase_alphanumeric_dash_underscore'],
        'name'         => ['strip_tags', 'trim_spaces', 'title_case'],
    ];

    public function segment()
    {
        return $this->belongsTo(Segment::class, 'segment_code', 'code');
    }

    public function vehicleModels()
    {
        return $this->hasMany(VehicleModel::class, 'sub_segment_code', 'code');
    }

    public function variants()
    {
        return $this->hasMany(Variant::class, 'sub_segment_code', 'code');
    }

    public static function generateCode(string $name): string
    {
        $map = [
            'XUV'     => 'XUV',
            'NON XUV' => 'NXUV',
            'NON-XUV' => 'NXUV',
        ];
        $upper = strtoupper(trim($name));
        return $map[$upper]
            ?? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 5));
    }
}

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

    protected $fillable = [
        'segment_code',
        'code',
        'oem_name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected array $columnTransformations = [

        'segment_code' => [
            'trim',
            'uppercase_alphanumeric_dash_underscore'
        ],

        'code' => [
            'trim',
            'uppercase_alphanumeric_dash_underscore'
        ],

        'oem_name' => [
            'strip_tags',
            'trim_spaces',
            'title_case'
        ],
    ];

    public function segment()
    {
        return $this->belongsTo(
            Segment::class,
            'segment_code',
            'code'
        );
    }

    public function vehicleModels()
    {
        return $this->hasMany(
            VehicleModel::class,
            'sub_segment_code',
            'code'
        );
    }

    public function variants()
    {
        return $this->hasMany(
            Variant::class,
            'sub_segment_code',
            'code'
        );
    }

    /**
     * XUV → XUV
     * NON XUV → NXUV
     */
    public static function generateCode(string $oem_name): string
    {
        $map = [
            'XUV'     => 'XUV',
            'NON XUV' => 'NXUV',
            'NON-XUV' => 'NXUV',
        ];

        $upper = strtoupper(trim($oem_name));

        return $map[$upper]
            ?? strtoupper(
                substr(
                    preg_replace('/[^A-Za-z0-9]/', '', $oem_name),
                    0,
                    5
                )
            );
    }
}
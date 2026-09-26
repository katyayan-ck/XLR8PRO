<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use App\Models\Traits\HasColumnTransformations;
use App\Services\Vehicle\SubSegmentService;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

class SubSegment extends BaseModel
{
    use CrudTrait;
    use HasColumnTransformations;

    protected $table = 'xlr8_vehicle_subsegment';

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
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** Field formats, transforms and rules live in the entity service (DEC-050). */
    protected string $entityService = SubSegmentService::class;

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
            'XUV' => 'XUV',
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

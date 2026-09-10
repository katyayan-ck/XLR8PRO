<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use App\Models\Traits\HasColumnTransformations;

class Color extends BaseModel
{
    use CrudTrait;
    use HasColumnTransformations;

    protected $table = 'xlr8_vehicle_color';

    protected $fillable = [
        'segment_code',
        'sub_segment_code',
        'model_code',
        'variant_code',

        'code',
        'name',
        'hex_code',
        'image',

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

    protected array $columnTransformations = [

        'segment_code' => [
            'trim',
            'uppercase_alphanumeric_dash_underscore'
        ],

        'sub_segment_code' => [
            'trim',
            'uppercase_alphanumeric_dash_underscore'
        ],

        'model_code' => [
            'trim',
            'uppercase_alphanumeric_dash_underscore'
        ],

        'variant_code' => [
            'trim',
            'uppercase_alphanumeric_dash_underscore'
        ],

        'code' => [
            'trim',
            'uppercase_alphanumeric_dash_underscore'
        ],

        'name' => [
            'strip_tags',
            'trim_spaces',
            'title_case'
        ],

        'hex_code' => [
            'trim',
            'uppercase'
        ],

        'image' => [
            'trim'
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

    public function subSegment()
    {
        return $this->belongsTo(
            SubSegment::class,
            'sub_segment_code',
            'code'
        );
    }

    public function vehicleModel()
    {
        return $this->belongsTo(
            VehicleModel::class,
            'model_code',
            'code'
        );
    }

    public function variant()
    {
        return $this->belongsTo(
            Variant::class,
            'variant_code',
            'code'
        );
    }

    public function variants()
    {
        return $this->belongsToMany(
            Variant::class,
            'variant_colors'
        );
    }

    /**
     * Extract color.code from vehicle_info.model_code
     * BM12AH515MB01D00JD → JD
     */
    public static function codeFromModelCode(
        string $vehicleInfoModelCode
    ): string
    {
        return strtoupper(
            substr(
                trim($vehicleInfoModelCode),
                -2
            )
        );
    }
}
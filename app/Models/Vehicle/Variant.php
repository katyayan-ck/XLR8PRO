<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use App\Helpers\KeywordHelper;
use App\Models\Utilities\KeyValue\Keyvalue;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use App\Models\Traits\HasColumnTransformations;

class Variant extends BaseModel
{
    use CrudTrait;
    use HasColumnTransformations;

    protected $table = 'xlr8_vehicle_variant';

    protected $fillable = [
        'segment_code',
        'sub_segment_code',
        'model_code',

        'code',
        'oem_name',
        'custom_name',
        'display_name',

        'permit_id',
        'taxi_price',
        'fuel_type_id',

        'seating_capacity',
        'wheels',
        'gvw',

        'cc_capacity',
        'transmission',
        'drivetrain',

        'body_type_id',
        'body_make_id',

        'is_csd',
        'csd_index',

        'status_id',
        'is_active',

        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'seating_capacity' => 'integer',
        'wheels'           => 'integer',
        'gvw'              => 'integer',
        'is_csd'           => 'boolean',
        'is_active'        => 'boolean',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
        'deleted_at'       => 'datetime',
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

        'code' => [
            'trim',
            'uppercase_alphanumeric_dash_underscore'
        ],

        'oem_name' => [
            'strip_tags',
            'trim_spaces',
            'title_case'
        ],

        'custom_name' => [
            'strip_tags',
            'trim_spaces',
            'title_case'
        ],

        'display_name' => [
            'strip_tags',
            'trim_spaces',
            'title_case'
        ],

        'taxi_price' => [
            'trim'
        ],

        'cc_capacity' => [
            'trim'
        ],

        'transmission' => [
            'strip_tags',
            'trim_spaces',
            'title_case'
        ],

        'drivetrain' => [
            'trim',
            'uppercase'
        ],

        'csd_index' => [
            'trim'
        ],
    ];

    // ── Code-based parent relationships ──────────────────────────

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

    // ── KKV relationships ────────────────────────────────────────

    public function permit()
    {
        return $this->belongsTo(
            Keyvalue::class,
            'permit_id'
        );
    }

    public function fuelType()
    {
        return $this->belongsTo(
            Keyvalue::class,
            'fuel_type_id'
        );
    }

    public function bodyType()
    {
        return $this->belongsTo(
            Keyvalue::class,
            'body_type_id'
        );
    }

    public function bodyMake()
    {
        return $this->belongsTo(
            Keyvalue::class,
            'body_make_id'
        );
    }

    public function statusKkv()
    {
        return $this->belongsTo(
            Keyvalue::class,
            'status_id'
        );
    }

    // ── Color relationships ──────────────────────────────────────

    public function colors()
    {
        return $this->belongsToMany(
            Color::class,
            'variant_colors'
        );
    }

    // ── Accessors ────────────────────────────────────────────────

    public static function getPermitOptions(): array
    {
        return KeywordHelper::options('permit');
    }

    public static function getFuelTypeOptions(): array
    {
        return KeywordHelper::options('fuel_type');
    }

    public static function getBodyTypeOptions(): array
    {
        return KeywordHelper::options('body_type');
    }

    public static function getBodyMakeOptions(): array
    {
        return KeywordHelper::options('body_make');
    }

    public static function getStatusOptions(): array
    {
        return KeywordHelper::options('vehicle_status');
    }

    /**
     * Derive variant.code from vehicle_info.model_code
     * BM12AH515MB01D00JD → BM12AH515MB01D00
     */
    public static function codeFromModelCode(
        string $vehicleInfoModelCode
    ): string
    {
        return substr(
            strtoupper(trim($vehicleInfoModelCode)),
            0,
            -2
        );
    }
}
<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use App\Models\Utilities\KeyValue\Keyvalue;
use App\Services\KeywordValueService;
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

        // colour lives on the variant row (one row per colour)
        'color',
        'color_code',

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
        'shield_pack',

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
            'uppercase_alphanumeric_dash_underscore',
        ],

        'sub_segment_code' => [
            'trim',
            'uppercase_alphanumeric_dash_underscore',
        ],

        'model_code' => [
            'trim',
            'uppercase_alphanumeric_dash_underscore',
        ],

        'code' => [
            'trim',
            'uppercase_alphanumeric_dash_underscore',
        ],

        'oem_name' => [
            'strip_tags',
            'trim_spaces',
            'title_case',
        ],

        'custom_name' => [
            'strip_tags',
            'trim_spaces',
            'title_case',
        ],

        'display_name' => [
            'strip_tags',
            'trim_spaces',
            'title_case',
        ],

        'color' => [
            'strip_tags',
            'trim_spaces',
            'title_case',
        ],

        'color_code' => [
            'trim',
            'uppercase',
        ],

        'taxi_price' => [
            'trim',
        ],

        'cc_capacity' => [
            'trim',
        ],

        'transmission' => [
            'strip_tags',
            'trim_spaces',
            'title_case',
        ],

        'drivetrain' => [
            'trim',
            'uppercase',
        ],

        'csd_index' => [
            'trim',
        ],

        'shield_pack' => [
            'strip_tags',
            'trim_spaces',
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

    // ── Options (KeywordValueService — no KeywordHelper) ─────────

    /**
     * id => value map for FK selects (permit_id, fuel_type_id, …).
     */
    protected static function kkvOptions(string $keywordCode): array
    {
        $keywordCode = strtoupper(trim($keywordCode));

        // Prefer id => value for FK form selects
        $rows = Keyvalue::query()
            ->where('keyword_code', $keywordCode)
            ->where('is_active', true)
            ->orderBy('value')
            ->get(['id', 'code', 'value']);

        if ($rows->isNotEmpty()) {
            return $rows->pluck('value', 'id')->toArray();
        }

        // Fallback: code => value from cached enum
        return KeywordValueService::getEnum($keywordCode, true);
    }

    public static function getPermitOptions(): array
    {
        return self::kkvOptions('PERMIT');
    }

    public static function getFuelTypeOptions(): array
    {
        return self::kkvOptions('FUEL_TYPE');
    }

    public static function getBodyTypeOptions(): array
    {
        return self::kkvOptions('BODY_TYPE');
    }

    public static function getBodyMakeOptions(): array
    {
        return self::kkvOptions('BODY_MAKE');
    }

    public static function getStatusOptions(): array
    {
        return self::kkvOptions('VEHICLE_STATUS');
    }

    /**
     * Base variant code (without colour suffix) from full Model Code.
     * BM12AH515MB01D00JD → BM12AH515MB01D00
     */
    public static function codeFromModelCode(string $vehicleInfoModelCode): string
    {
        return substr(strtoupper(trim($vehicleInfoModelCode)), 0, -2);
    }

    /**
     * Colour code = last 2 chars of full Model Code.
     */
    public static function colorCodeFromModelCode(string $vehicleInfoModelCode): string
    {
        return substr(strtoupper(trim($vehicleInfoModelCode)), -2);
    }
}

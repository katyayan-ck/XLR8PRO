<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use App\Models\Traits\HasColumnTransformations;
use App\Services\Vehicle\VehicleModelService;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

/**
 * @property int $id
 * @property string|null $segment_code
 * @property string|null $sub_segment_code
 * @property string $code
 * @property string|null $name
 * @property string|null $oem_name
 * @property string|null $custom_name
 * @property bool $is_active
 */
class VehicleModel extends BaseModel
{
    use CrudTrait;
    use HasColumnTransformations;

    protected $table = 'xlr8_vehicle_model';

    protected $fillable = [
        'segment_code',
        'sub_segment_code',
        'code',
        'name',
        'oem_name',
        'custom_name',
        'description',
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
    protected string $entityService = VehicleModelService::class;

    // ── Relationships ─────────────────────────────────────────────

    public function brand()
    {
        return $this->belongsTo(
            Brand::class,
            'brand_code',
            'code'
        );
    }

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

    public function variants()
    {
        return $this->hasMany(
            Variant::class,
            'model_code',
            'code'
        );
    }

    public function colors()
    {
        return $this->hasMany(
            Color::class,
            'model_code',
            'code'
        );
    }

    // ── Code Generation ───────────────────────────────────────────

    /**
     * Derive model code from Custom Model name.
     * "BE6" → "BE6"
     * "XUV 700" → "XUV700"
     * "Scorpio N" → "SCORN"
     */
    public static function generateCode(string $customModelName): string
    {
        $clean = strtoupper(
            preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                $customModelName
            )
        );

        return substr($clean, 0, 10);
    }

    /**
     * DEC-092: model images (many) and the PDF brochure (one). 250 px preview + 100 px thumb for images.
     */
    public function registerMediaCollections(): void
    {
        parent::registerMediaCollections();
        $this->addMediaCollection('images')->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->registerMediaConversions(function () {
                $this->addMediaConversion('preview')->width(250)->height(250)->quality(75);
                $this->addMediaConversion('thumb')->width(100)->height(100)->quality(70);
            });
        $this->addMediaCollection('brochure')->useDisk('public')->singleFile()->acceptsMimeTypes(['application/pdf']);
    }

    /** DEC-092: this model's specification values (item code => row). */
    public function specs()
    {
        return $this->hasMany(ModelSpec::class, 'model_code', 'code');
    }
}

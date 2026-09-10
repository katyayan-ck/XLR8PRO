<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use App\Models\Traits\HasColumnTransformations;

class VehicleModel extends BaseModel
{
    use CrudTrait;
    use HasColumnTransformations;

    protected $table = 'xlr8_vehicle_model';

    /**
     * Live DB: segment_code, sub_segment_code, code, name, oem_name, is_active, audit.
     * NO custom_name column.
     */
    protected $fillable = [
        'segment_code',
        'sub_segment_code',
        'code',
        'name',
        'oem_name',
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
        'segment_code'     => ['trim', 'uppercase_alphanumeric_dash_underscore'],
        'sub_segment_code' => ['trim', 'uppercase_alphanumeric_dash_underscore'],
        'code'             => ['trim', 'uppercase_alphanumeric_dash_underscore'],
        'name'             => ['strip_tags', 'trim_spaces', 'title_case'],
        'oem_name'         => ['strip_tags', 'trim_spaces', 'title_case'],
    ];

    public function segment()
    {
        return $this->belongsTo(Segment::class, 'segment_code', 'code');
    }

    public function subSegment()
    {
        return $this->belongsTo(SubSegment::class, 'sub_segment_code', 'code');
    }

    public function variants()
    {
        return $this->hasMany(Variant::class, 'model_code', 'code');
    }

    public static function generateCode(string $customModelName): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $customModelName));
        return substr($clean, 0, 10);
    }
}

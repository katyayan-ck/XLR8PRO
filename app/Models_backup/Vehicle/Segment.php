<?php

namespace App\Models\Vehicle;

use App\Models\BaseModel;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use App\Models\Traits\HasCommunications;
use App\Models\Traits\HasColumnTransformations;

class Segment extends BaseModel
{
    use CrudTrait;
    use HasCommunications;
    use HasColumnTransformations;

    protected $table = 'xlr8_vehicle_segment';

    protected $fillable = [
        'code',
        'name',
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

        'code' => [
            'trim',
            'uppercase_alphanumeric_dash_underscore'
        ],

        'name' => [
            'strip_tags',
            'trim_spaces',
            'title_case'
        ],
    ];

    public function subSegments()
    {
        return $this->hasMany(
            SubSegment::class,
            'segment_code',
            'code'
        );
    }

    public function vehicleModels()
    {
        return $this->hasMany(
            VehicleModel::class,
            'segment_code',
            'code'
        );
    }

    public function variants()
    {
        return $this->hasMany(
            Variant::class,
            'segment_code',
            'code'
        );
    }

    /**
     * Generate a stable code from segment name
     * BEV → BEV
     * PERSONAL → PERSL
     * COMMERCIAL → COMML
     * LMM → LMM
     */
    public static function generateCode(string $name): string
    {
        $map = [
            'BEV'        => 'BEV',
            'PERSONAL'   => 'PERSL',
            'COMMERCIAL' => 'COMML',
            'LMM'        => 'LMM',
        ];

        $upper = strtoupper(trim($name));

        return $map[$upper]
            ?? strtoupper(
                substr(
                    preg_replace('/[^A-Za-z0-9]/', '', $name),
                    0,
                    5
                )
            );
    }
}
<?php

namespace App\Models\CRM;

use App\Models\BaseModel;
use App\Models\User;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\VehicleModel;
use Illuminate\Support\Facades\Cache;

class Campaign extends BaseModel
{
    protected $table = 'xlr8_crm_campaigns';

    protected $fillable = [
        'name',
        'segment_code',
        'model_code',
        'activity_code',
        'start_date',
        'end_date',
        'branch_code',
        'location_code',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->casts = array_merge($this->casts, [
            'start_date' => 'date',
            'end_date'   => 'date',
        ]);
    }

    protected array $columnTransformations = [
        'name'          => 'trim|ucwords',
        'segment_code'  => 'uppercase|trim',
        'model_code'    => 'uppercase|trim',
        'activity_code' => 'uppercase|trim',
        'branch_code'   => 'uppercase|trim',
        'location_code' => 'uppercase|trim',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function segment()
    {
        return $this->belongsTo(
            Segment::class,
            'segment_code',
            'code'
        );
    }

    public function model()
    {
        return $this->belongsTo(
            VehicleModel::class,
            'model_code',
            'code'
        );
    }

    public function createdBy()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updatedBy()
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function deletedBy()
    {
        return $this->belongsTo(
            User::class,
            'deleted_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getSegmentNameAttribute()
    {
        return $this->segment?->name;
    }

    public function getModelNameAttribute()
    {
        return $this->model?->name;
    }

    /*
    |--------------------------------------------------------------------------
    | Cache Helpers
    |--------------------------------------------------------------------------
    */

    public static function clearCache(): void
    {
        Cache::forget('crm_campaigns');
    }
}
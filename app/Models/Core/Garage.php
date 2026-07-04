<?php

namespace App\Models\Core;

use App\Models\BaseModel;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\Person;

class Garage extends BaseModel
{
    use CrudTrait;
    use HasFactory;
    protected $table = 'garages';

    protected $fillable = [
        'person_id',
        'name',
        'type',
        'address',
        'city',
        'state',
        'pincode',
        'latitude',
        'longitude',
        'contact_person',
        'mobile',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationship: Person
     */
    public function person()
    {
        return $this->belongsTo(Person::class);
    }
}

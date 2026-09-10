<?php
namespace App\Models\Vehicle;

use App\Models\BaseModel;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

class Brand extends BaseModel
{
    use CrudTrait;

    protected $table = 'xlr8_vehicle_brand';

    protected $fillable = ['code', 'name', 'description', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function segments()
    {
        return $this->hasMany(Segment::class, 'brand_code', 'code');
    }

    public function vehicleModels()
    {
        return $this->hasMany(VehicleModel::class, 'brand_code', 'code');
    }

    public function variants()
    {
        return $this->hasMany(Variant::class, 'brand_code', 'code');
    }

    public static function generateCode(string $name): string
    {
        // Take first 3-5 meaningful chars, uppercase
        $slug = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));
        return substr($slug, 0, 5);
    }
}

<?php

namespace App\Models\Admin;

use App\Models\BaseModel;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User; 

/**
 * UserType Model
 * 
 * Types of users in system (Admin, Employee, Customer, Vendor, etc.)
 */
class UserType extends BaseModel
{
    use CrudTrait;
    use HasFactory;
    protected $table = 'xlr8_iam_user_type';

    protected $fillable = [
        'code',
        'display_name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Users of this type
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Generate auto code
     */
    public static function generateCode($prefix = 'UT')
    {
        $lastId = self::max('id') ?? 0;
        return $prefix . '-' . str_pad($lastId + 1, 2, '0', STR_PAD_LEFT);
    }
}

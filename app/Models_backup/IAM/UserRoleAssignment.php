<?php

namespace App\Models\IAM;

use App\Models\BaseModel;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User; 

/**
 * UserRoleAssignment Model
 * 
 * Links users to roles with date validity support
 * Allows users to have multiple roles
 */
class UserRoleAssignment extends BaseModel
{
    use CrudTrait;
    use HasFactory;
    protected $table = 'xlr8_iam_user_role_pivot';

    protected $fillable = [
        'user_id',
        'role_id',
        'from_date',
        'to_date',
        'is_current',
        'remarks',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'is_current' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationship: User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Role
     */
    public function role()
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class);
    }

    /**
     * Scope: Current roles
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true)
            ->where(function ($q) {
                $q->whereNull('to_date')
                    ->orWhere('to_date', '>=', now());
            });
    }
}

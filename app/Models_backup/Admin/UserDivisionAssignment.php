<?php

namespace App\Models\Admin;

use App\Models\BaseModel;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User; 

/**
 * UserDivisionAssignment Model
 * 
 * Links users to divisions for access control
 */
class UserDivisionAssignment extends BaseModel
{
    use CrudTrait;
    use HasFactory;
    protected $table = 'xlr8_iam_user_division_pivot';

    protected $fillable = [
        'user_id',
        'division_id',
        'from_date',
        'to_date',
        'is_current',
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
     * Relationship: Division
     */
    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Scope: Current assignments
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }
}

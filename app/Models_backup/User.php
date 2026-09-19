<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Admin\Employee;
use App\Models\Admin\Person;
use App\Models\Admin\UserScope;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Services\OrgService;

class User extends Authenticatable
{
    use CrudTrait, Notifiable, SoftDeletes, HasFactory, HasRoles;

    protected $table = 'users';

    protected $fillable = [
        'username',
        'password',
        'user_type',
        'person_code',
        'employee_code',
        'user_type_id',
        'avatar',
        'is_active',
        'bypass_data_scoping',
        'last_login_at',
        'remember_token',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active'           => 'boolean',
        'bypass_data_scoping' => 'boolean',
        'last_login_at'       => 'datetime',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
        'deleted_at'          => 'datetime',
    ];


    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_code', 'code');
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_code', 'person_code');
    }

    public function scopes()
    {
        return $this->hasMany(UserScope::class);
    }

    public function activeScopes()
    {
        return $this->scopes()->where('is_active', true);
    }

       public function getAccessProfileAttribute(): ?array
    {
        return OrgService::getCurrentUser();
    }

    

    public function primaryBranchCode(): ?string
    {
        return $this->employee?->primary_branch_code;
    }

    public function primaryLocationCode(): ?string
    {
        return $this->employee?->primary_loc_code;
    }

    public function primaryDepartmentCode(): ?string
    {
        return $this->employee?->primary_dept_code;
    }

    public function primaryDivisionCode(): ?string
    {
        return $this->employee?->primary_div_code;
    }

    public function primaryPost(): ?string
    {
        return $this->employee?->desig_code ?? $this->employee?->designation_code ?? '—';
    }

    public function getPrimaryMobileAttribute(): ?string
    {
        return $this->person?->primary_mobile;
    }

    public function getPrimaryEmailAttribute(): ?string
    {
        return $this->person?->primary_email;
    }

    public function getAllMobilesAttribute()
    {
        return $this->person?->all_mobiles ?? collect();
    }

    public function getAllEmailsAttribute()
    {
        return $this->person?->all_emails ?? collect();
    }

    public function branches()
    {
        return $this->belongsToMany(\App\Models\Admin\Branch::class, 'xlr8_admin_emp_branch_pivot', 'employee_code', 'branch_code');
    }

    public function locations()
    {
        return $this->belongsToMany(\App\Models\Admin\Location::class, 'xlr8_admin_emp_location_pivot', 'employee_code', 'location_code');
    }

    public function departments()
    {
        return $this->belongsToMany(\App\Models\Admin\Department::class, 'xlr8_admin_emp_department_pivot', 'employee_code', 'dept_code');
    }

    public function divisions()
    {
        return $this->belongsToMany(\App\Models\Admin\Division::class, 'xlr8_admin_emp_division_pivot', 'employee_code', 'div_code');
    }

   

    public function hasScope(string $type, string $code): bool
    {
        $type = strtoupper(trim($type));
        $code = strtoupper(trim($code));

        return $this->activeScopes()
            ->where('scope_type', $type)
            ->where('scope_code', $code)
            ->exists();
    }

    public function bypassesDataScoping(): bool
    {
        return (bool) $this->bypass_data_scoping;
    }

    public function getScopeCodes(string $type): array
    {
        $type = strtoupper(trim($type));
        return $this->activeScopes()
            ->where('scope_type', $type)
            ->pluck('scope_code')
            ->map(fn($c) => strtoupper($c))
            ->toArray();
    }

    public function getAllScopes(): array
    {
        return $this->activeScopes()
            ->get()
            ->groupBy('scope_type')
            ->map(fn($items) => $items->pluck('scope_code')->map(fn($c) => strtoupper($c))->toArray())
            ->toArray();
    }


    public function getDisplayNameAttribute(): string
    {
        return $this->person?->display_name 
            ?? $this->employee?->person?->display_name 
            ?? $this->username 
            ?? 'N/A';
    }

    public function getAvatarInitialsAttribute(): string
    {
        $name = $this->display_name;
        $words = explode(' ', trim($name));
        $initials = '';
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return substr($initials, 0, 2) ?: 'U';
    }

    public function getPrimaryDesignationAttribute(): ?string
    {
        return $this->employee?->designation?->name 
            ?? $this->employee?->desig_code 
            ?? null;
    }

    public function getAllAccessScopesAttribute(): array
    {
        return $this->activeScopes()
            ->get()
            ->groupBy('scope_type')
            ->map(fn($items) => $items->pluck('scope_code')->unique()->values()->toArray())
            ->toArray();
    }

   

    protected static function boot()
    {
        parent::boot();
    }
}

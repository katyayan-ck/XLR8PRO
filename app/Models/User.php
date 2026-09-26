<?php

namespace App\Models;

use App\Models\Admin\Branch;
use App\Models\Admin\Department;
use App\Models\Admin\Division;
use App\Models\Admin\Employee;
use App\Models\Admin\Location;
use App\Models\Admin\Person;
use App\Models\Admin\UserScope;
use App\Models\IAM\UserDeviceToken;
use App\Models\IAM\UserPermissionDenial;
use App\Services\OrgService;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use CrudTrait, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $table = 'users';

    /**
     * Roles and permissions are minted with guard `web`. Admin requests switch the default
     * guard to `backpack` (BUG-055), so Spatie must not derive the guard from the default.
     */
    protected string $guard_name = 'web';

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
        'is_active' => 'boolean',
        'bypass_data_scoping' => 'boolean',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_code', 'code');
    }

    /**
     * The "removed" half of user-level permission overrides — a role-granted
     * permission explicitly revoked for this user only. Checked by a
     * Gate::before() hook in AppServiceProvider before any permission
     * resolves. The "added" half is Spatie's own native
     * givePermissionTo()/model_has_permissions — no extra relation needed
     * for that, $this->getDirectPermissions() already covers it.
     */
    public function permissionDenials()
    {
        return $this->hasMany(UserPermissionDenial::class);
    }

    public function deniesPermission(string $permissionName): bool
    {
        return $this->permissionDenials()
            ->whereHas('permission', fn ($q) => $q->where('name', $permissionName))
            ->exists();
    }

    /**
     * Diff against the user's role-inherited permissions — used by the
     * "Permission Block (existing and overriding)" card on the user show
     * page. 'added' = direct grants not covered by any role; 'removed' =
     * explicit denials of an otherwise role-granted permission.
     *
     * @return array{added: array<int, string>, removed: array<int, string>}
     */
    public function permissionOverrides(): array
    {
        $roleNames = $this->getPermissionsViaRoles()->pluck('name');
        $direct = $this->getDirectPermissions()->pluck('name');
        $denied = $this->permissionDenials()->with('permission')->get()
            ->pluck('permission.name')->filter()->values();

        return [
            'added' => $direct->diff($roleNames)->values()->all(),
            'removed' => $denied->intersect($roleNames)->values()->all(),
        ];
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_code', 'person_code');
    }

    public function scopes()
    {
        return $this->hasMany(UserScope::class);
    }

    public function deviceTokens()
    {
        return $this->hasMany(UserDeviceToken::class);
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

    public function getAllAddressesAttribute()
    {
        return $this->person?->addresses ?? collect();
    }

    public function getAllBankingAttribute()
    {
        return $this->person?->bankingDetails ?? collect();
    }

    public function isEmployee(): bool
    {
        return $this->employee()->exists();
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'xlr8_admin_emp_branch_pivot', 'employee_code', 'branch_code');
    }

    public function locations()
    {
        return $this->belongsToMany(Location::class, 'xlr8_admin_emp_location_pivot', 'employee_code', 'location_code');
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'xlr8_admin_emp_department_pivot', 'employee_code', 'dept_code');
    }

    public function divisions()
    {
        return $this->belongsToMany(Division::class, 'xlr8_admin_emp_division_pivot', 'employee_code', 'div_code');
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

    /**
     * Wildcard RBAC bypass. Backed by the Spatie 'superadmin' role (matches the
     * role slug already used by UserImporter::assignRolesAndPermissions()).
     * isSuperAdmin() is distinct from bypassesDataScoping() — see known-pitfalls.md P-16.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    public function getScopeCodes(string $type): array
    {
        $type = strtoupper(trim($type));

        return $this->activeScopes()
            ->where('scope_type', $type)
            ->pluck('scope_code')
            ->map(fn ($c) => strtoupper($c))
            ->toArray();
    }

    public function getAllScopes(): array
    {
        return $this->activeScopes()
            ->get()
            ->groupBy('scope_type')
            ->map(fn ($items) => $items->pluck('scope_code')->map(fn ($c) => strtoupper($c))->toArray())
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
            ->map(fn ($items) => $items->pluck('scope_code')->unique()->values()->toArray())
            ->toArray();
    }

    protected static function boot()
    {
        parent::boot();
    }
}

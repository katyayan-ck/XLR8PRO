# Skill: XCELR8 RBAC & Scope Debug

**When to activate:** Any task involving: permission denied, unauthorized, 403 error, user can't see data, wrong branch data, data scoping issue, RBAC problem, role/permission investigation, missing permissions, access control.

**Keyword triggers:** permission denied, 403, unauthorized, RBAC, role, permission, scope, data scoping, branch access, can't see, wrong data, access control, UserScope, OrgScope, designation.

---

## Investigation Protocol

When investigating an RBAC or data access issue, follow this exact sequence:

### Step 1: Identify the user and their roles
```sql
-- Get user details
SELECT id, email, person_code FROM users WHERE email = '{email}';

-- Get current active roles (Spatie)
SELECT r.name as role_name, ur.model_id as user_id
FROM roles r
JOIN model_has_roles ur ON r.id = ur.role_id
WHERE ur.model_id = {userId} AND ur.model_type = 'App\\Models\\User';

-- Get temporal role assignments
SELECT * FROM xlr8_iam_user_role_assignments 
WHERE user_id = {userId} 
AND (valid_until IS NULL OR valid_until > NOW())
AND is_active = 1;
```

### Step 2: Inspect permissions for those roles
```sql
-- Get all permissions for a role
SELECT p.name as permission
FROM permissions p
JOIN role_has_permissions rp ON p.id = rp.permission_id
JOIN roles r ON r.id = rp.role_id
WHERE r.name = '{roleName}';

-- Or use Tinker
// $user->getAllPermissions()->pluck('name')
```

### Step 3: Check SuperAdmin / bypass flags
```sql
SELECT bypass_data_scoping, is_super_admin 
FROM users WHERE id = {userId};
-- Also check Spatie wildcard role
```

### Step 4: Inspect the data scope
```sql
-- What branches/locations can this user see?
SELECT * FROM xlr8_admin_user_scopes 
WHERE user_id = {userId} AND is_active = 1;
```

### Step 5: Check OrgService resolution
```php
// In Tinker
$user = User::find({userId});
$employee = $user->employee;
$scopes = app(OrgScopeService::class)->getUserScopes($user);
// Inspect: allowed branches, departments, divisions
```

### Step 6: Inspect the specific route/middleware
```php
// Check what middleware the failing route has
php artisan route:list --name={routeName}
// Look at the 'middleware' column for permission: entries
```

### Step 7: Check Backpack CRUD controller setup
```php
// In the CrudController's setup():
// Which permission middleware is applied?
// Is it checking the right MODULE_PROCESS_ACTIVITY code?
```

### Step 8: Verify with a known-good user
```php
// In Tinker — compare a user who CAN access vs one who CANNOT
$goodUser = User::find({goodUserId});
$badUser = User::find({badUserId});
$goodUser->can('SLS_BKNG_VIEW'); // should be true
$badUser->can('SLS_BKNG_VIEW');  // should be false — why?
// Compare their roles, permissions, and scopes
```

---

## Common Root Causes

| Symptom | Likely Cause |
|---|---|
| User gets 403 | Missing `MODULE_PROCESS_ACTIVITY` permission on their role |
| User sees no records | Data scope not configured in `xlr8_admin_user_scopes` |
| User sees too many records | `bypass_data_scoping = true` unexpectedly set |
| Job returns empty results | Job not using `withoutDataScope()` |
| Permission check fails in Backpack | CRUD controller using wrong permission code string |
| Newly added permission not working | `php artisan permission:cache-reset` not run after assignment |
| Role permissions seem wrong | Cache stale — run `php artisan cache:clear` or `permission:cache-reset` |

---

## Implementation Checklist

When adding a new permission:

```php
// 1. Define permission with standard code
$permission = Permission::findOrCreate('SLS_BKNG_RCPAY', 'web');

// 2. Assign to relevant role
$role = Role::findByName('Sales Manager', 'web');
$role->givePermissionTo('SLS_BKNG_RCPAY');

// 3. Protect the route
Route::post('/{booking}/receive-payment', ...)
    ->middleware('permission:SLS_BKNG_RCPAY')
    ->name('receive-payment');

// 4. Or protect in FormRequest
public function authorize(): bool {
    return $this->user()->can('SLS_BKNG_RCPAY');
}

// 5. Clear permission cache
php artisan permission:cache-reset
```

---

## Seeder Reference

Permission seeder should register all MODULE_PROCESS_ACTIVITY codes:

```php
// In DatabaseSeeder or PermissionSeeder
$permissions = [
    'SLS_BKNG_CREATE',
    'SLS_BKNG_EDIT',
    'SLS_BKNG_VIEW',
    'SLS_BKNG_RCPAY',
    'SLS_BKNG_CANCEL',
    'PRC_VEH_MANAGE', // maps to manage_pricing
    // ...
];

foreach ($permissions as $permission) {
    Permission::findOrCreate($permission, 'web');
    Permission::findOrCreate($permission, 'api'); // if needed for API guard
}
```

---

## Key Tables

```sql
roles                          -- Spatie roles
permissions                    -- Spatie permissions (format: MODULE_PROCESS_ACTIVITY)
model_has_roles                -- User → Role assignments
model_has_permissions          -- Direct user permissions (rare)
role_has_permissions           -- Role → Permission assignments
xlr8_iam_user_role_assignments -- Temporal role overrides
xlr8_admin_user_scopes         -- Branch/location/dept/div access
users                          -- bypass_data_scoping, is_super_admin flags
```

# IAM & auth — users, designations-as-roles, permissions, scopes, mobile OTP login

Who may log in, what they may do (permissions), and which rows they may see (data scopes). Admin panel users log in
with Backpack (username + password); the mobile app logs in with a mobile OTP and gets a device-bound Sanctum token.

| Need | Use |
|---|---|
| check an action | `backpack_user()->can('SLS_BKNG_EDIT')` — first line of every admin action (`.ai/rules/admin-backpack.md`) |
| create / edit a login | `App\Services\IAM\UserService` (entity service) |
| give / take / set data scopes | `App\Services\IAM\UserScopeService::grant()`, `revoke()`, `sync()` |
| a designation's permissions | `Org\DesignationService::syncPermissions()` → `RolePermissionService` |
| permission tree for a screen | `PermissionTreeService::buildTree()` |
| scope → ids for a filter | `DataScopeService::getAccessibleIds()` |
| mobile OTP login | `AuthService` (API v1) |
| RBAC workbook export | `UserRbacExportService` (+ `php artisan` export command) |

---

## Concepts
- **Permission code** `{MOD}_{PROC}_{ACT}` (e.g. `SLS_BKNG_KYC`, `UTL_TASK_VIEW`), Spatie `permissions` table, guard
  `web`, with `module_code` + `process_code` columns. Module / process masters: `xlr8_iam_module`, `xlr8_iam_process`.
- **Roles are designations**: Spatie's roles table is configured as `xlr8_admin_designation`. A user's role = their
  designation; `superadmin` is the bypass role (Gate `before` hook in `AppServiceProvider`).
- **User-level overrides**: extra direct permissions ("added") and `UserPermissionDenial` rows ("removed") checked by a
  Gate `before` hook. See `User::permissionOverrides()`.
- **Data scopes** (`xlr8_admin_user_scopes`): rows `(user_id, scope_type, scope_code)` for `branch`, `location`,
  `department`, `division`, `vertical`, `segment`, `sub_segment`, `model`, `variant`. **Enforcement is not switched on**
  (`ScopedQuery` / `ScopedCrud` dormant, BUG-083) — screens that filter call `DataScopeService` explicitly.
- `bypass_data_scoping` on a user = sees all rows; distinct from `superadmin` (P-16).

## Models
| Model | Table | Notes |
|---|---|---|
| `User` | `users` | see [core.md](core.md) |
| `IAM\Permission` | `permissions` | Spatie permission + `module()`, `process()` (by code) |
| `IAM\Module` | `xlr8_iam_module` | `processes()`, `permissions()` (through processes); key `code` |
| `IAM\Process` | `xlr8_iam_process` | `module()`, `permissions()`; route key `code` |
| `IAM\Role` | designation table | Spatie role view of designations; scopes `systemRoles()`, `posts()`, `active()`; `permissions_count` |
| `Admin\Designation` | `xlr8_admin_designation` | the same rows as the real master — see [org.md](org.md) |
| `IAM\UserPermissionDenial` | `xlr8_iam_user_permission_denials` | `user()`, `permission()` |
| `IAM\UserRoleAssignment` | `xlr8_iam_user_role_pivot` | dated extra roles (`from_date`, `to_date`, `is_current`); scope `current()`; `user()`, `role()` |
| `Admin\UserScope` | `xlr8_admin_user_scopes` | `user()`; scopes `ofType($type)`, `forUser($id)`; revoked rows stay (`is_active = 0`, `to_date`) |
| `IAM\UserDeviceToken` | `xlr8_iam_user_device_token` | FCM tokens per device: scopes `active()`, `forUser()`, `forPlatform()`, `recentlyUsed($days = 7)`; `isValid()`, `markAsUsed()`, `recordNotificationSent()`, `deactivate()`, `activate()`, `display_name` |
| `IAM\DeviceSession` | `xlr8_iam_device_session` | the device a Sanctum token is bound to (`validate_device` middleware) |
| `IAM\OtpToken` | `xlr8_iam_otp_token` | hashed login OTPs: scopes `notExpired()`, `notUsed()`, `forMobile()`, `forUser()`; `isExpired()`, `isUsed()`, `markAsUsed()`, `getExpirationRemaining()` |
| `IAM\OtpAttemptLog` | `xlr8_iam_otp_attempt_log` | audit of OTP requests / verifies / logouts |
| `IAM\AccountLock` | `xlr8_iam_account_lock` | `locked_until` after repeated failures |

---

## UserService (entity service, `App\Services\IAM\UserService`)
Fields: `username` (lower-case `a-z 0-9 . _ - @`, ≤ 50, unique incl. deleted), `password` (≥ 8, exactly as typed,
hashed; blank on update keeps the current one), `user_type` (`Emp`, `Cust`, `DSA`, `Insurer`, `Associate`; default Emp),
`person_code`, `employee_code`, `is_active` (login active). Roles, overrides and `bypass_data_scoping` are **not** fields
— the calling workflow sets them.
```php
$user = app(UserService::class)->create(['username' => $emp->code, 'password' => $initial, 'person_code' => $emp->person_code,
    'employee_code' => $emp->code]);        // staff usernames = employee codes (OrgService hierarchy relies on it)
$user->syncRoles([$emp->designation_code]);  // role = designation
app(UserService::class)->update($user, ['is_active' => false]);   // suspend
```

## UserScopeService (entity service)
| Method | Returns |
|---|---|
| `grant(int $userId, string $type, string $code)` | `'inserted'` / `'activated'` (re-activated or restored) / `'unchanged'` |
| `revoke(UserScope $scope)` | void — sets `is_active = 0`, `to_date = today` (never deletes) |
| `sync(int $userId, string $type, array $codes)` | `['inserted' => n, 'activated' => n, 'deactivated' => n]` — the user's active codes of that type become exactly `$codes` |
`TYPES` maps each scope type to its master table (codes are validated against it).
```php
app(UserScopeService::class)->sync($user->id, 'branch', ['JPR', 'AJM']);
```

## RolePermissionService / DesignationService / PermissionTreeService / IAM\RbacService
| Class | Methods |
|---|---|
| `IAM\RolePermissionService` | `syncRolePermissions(Role $role, array $codes)`, `currentPermissionCodes(Role $role): array` — the only place that calls Spatie `syncPermissions` on roles |
| `Org\DesignationService` | `syncPermissions(Designation $d, array $codes)`, `currentPermissionCodes(Designation $d)` (delegate to the above) |
| `IAM\PermissionTreeService` | `buildTree(): list<['code', 'label', 'processes' => [['code', 'label', 'permissions' => [['code', 'label']]]]]>` parsed from permission names (unparseable ones under `LEGACY`), `flattenCodes($tree): list<string>` |
| `IAM\RbacService` (modules / processes) | `getActiveModules()`, `getActiveProcessesByModule($moduleCode)`, `getActiveProcessNamesByModule($moduleCode)`, `getPermissionNamesByProcess($processCode)`, `extractPermissionSuffix($name)` (`SLS_BKNG_KYC` → `KYC`), `updateModule($module, $attrs)` / `updateProcess($process, $attrs)` — refuse to deactivate a module that has active processes, or a process that has any permissions (throws `Exception`) |

```php
$tree = app(PermissionTreeService::class)->buildTree();          // Designation → Permissions panel
app(DesignationService::class)->syncPermissions($designation, $request->input('permissions', []));
```

## DataScopeService (`App\Services\IAM\DataScopeService`)
| Method | Returns |
|---|---|
| `getAccessibleIds(User $user, string $type)` | `null` = **unrestricted** (superadmin / bypass); `[]` = nothing; else primary-key **ids** of the scoped master rows (`TYPE_MODELS` maps type → model) |
| `getOrgScope($user, $type)` / `getVehicleScope($user, $type)` | aliases of the above |
```php
$ids = app(DataScopeService::class)->getAccessibleIds(backpack_user(), 'branch');
$query->when($ids !== null, fn ($q) => $q->whereIn('branch_id', $ids));   // null → no filter
```
Codes (not ids) are on the user: `$user->getScopeCodes('branch')`.

## AuthService (mobile OTP login, API v1)
Called by `Api\V1\AuthController`; responses are wrapped in the API envelope by the controller.

| Method | Returns / throws |
|---|---|
| `requestOtp(string $mobile, Request $r)` | `['success' => true, 'message' => …, 'data' => ['mobile', 'expires_at', 'expires_in_minutes' => 10 (+ 'otp' in local only)]]`; throws `ValidationException` (`AUTH_MOBILE_INVALID`), `AuthenticationException` (`AUTH_USER_NOT_FOUND`, `AUTH_USER_INACTIVE`), `RateLimitException` (> 5 requests / 15 min), `AccountLockedException` |
| `verifyOtp($mobile, $otp, $deviceId, $deviceName, $platform, Request $r)` | `['success' => true, 'data' => ['token', 'expires_at', 'user' => ['id', 'name', 'email', 'mobile', 'role']]]` — binds the device (`DeviceSession`), issues a Sanctum token with ability `device_id:<id>`; 5 wrong tries lock the account 30 min; throws `AuthenticationException` (`AUTH_OTP_INVALID`, `AUTH_OTP_EXPIRED`, user not found) |
| `getUserDetails(User $user)` | `['success', 'message', 'data' => ['id', 'name', 'email', 'mobile', 'role', 'permissions']]` |
| `logout(User $user)` | revokes the current token; `['success' => true, 'message' => 'Logged out successfully']` |

**Known issues:** mobile login fails today — the user lookup queries `users.mobile`, which doesn't exist, and
`name` / `email` / `mobile` in the responses are always null (BUG-187, repair awaits approval); the OTP uses `rand()`
(BUG-188). Logs carry masked numbers and never the OTP (BUG-189 fixed, DEC-070). New OTP flows should use `Sms::otp()` / `Sms::verify()`
(docs/utilities/11-sms.md), which are hashed, rate-limited and never logged.

## UserRbacExportService (DEC-040 workbook)
`permissionRows()`, `roleRows()`, `userRows()` (editable importer columns + read-only info), `scopeRows()` (one row per
user × type × code, compacted to `ALL`), `lists()` (dropdowns, scope lists start with `ALL`), `userHeaders()`,
`label($type, $code)`. Used by `UserRbacWorkbookExport` and the export command; the importer reads the same columns.

## Legacy: `App\Services\RBACService`
Injected into `UserCrudController` but not called. `canUserAccess()` builds `resource.action` names (never match),
`getUserPermissions()` references a missing relation — **do not use** (BUG-190). Other methods: `grantPermission()`,
`revokePermission()`, `hasWildcardAccess()`, `assignRole($user, $role, $from, $to)` (Spatie role + a
`UserRoleAssignment` row), `removeRole()`, `clearUserPermissionCache()`, `permissionExists()`, `getModulePermissions()`.

## Use cases
**Minting a permission for a new screen** — a migration inserts `{MOD}_{PROC}_{ACT}` with `guard_name = 'web'` and the
module / process codes (see `2026_09_28_100000_platform_permissions` for the pattern), then grant it to designations with
`DesignationService::syncPermissions()` or the Designation → Permissions screen.

**"Only my branches" list filter**
```php
$codes = backpack_user()->isSuperAdmin() || backpack_user()->bypassesDataScoping() ? null : backpack_user()->getScopeCodes('branch');
$query->when($codes !== null, fn ($q) => $q->whereIn('branch_code', $codes));
```

## Gotchas
- Never mint permissions with `guard_name = 'backpack'` — they silently never match.
- Never check role names (`hasRole('GM')`) in code; check permissions.
- After changing permissions in code, clear Spatie's cache: `php artisan permission:cache-reset`.
- `$this->middleware()` in controllers doesn't exist on Laravel 11+ (BUG-159) — check inline.

## Testing
`$this->actingAs($user, 'backpack')` for admin routes; for the API use a device-bound token (see
`.ai/rules/testing.md`). Superadmin: `User::role('superadmin')->first()`; a scoped user: any active non-superadmin user
without the permission under test (see `tests/Feature/Admin/SystemSettingScreensTest`).

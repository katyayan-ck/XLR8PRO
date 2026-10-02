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
| a user's effective scope / filter rows by it | `DataScope::current()` / `HasDataScope` (automatic, DEC-071) |
| mobile OTP login | `AuthService` (API v1) |
| users workbook (export / import, DEC-089) | `Org\UsersWorkbook\UsersWorkbookService` → `UserRowService` |
| RBAC workbook export (audit) | `UserRbacExportService` (+ `php artisan` export command) |

---

## Concepts
- **Permission code** `{MOD}_{PROC}_{ACT}` (e.g. `SLS_BKNG_KYC`, `UTL_TASK_VIEW`), Spatie `permissions` table, guard
  `web`, with `module_code` + `process_code` columns. Module / process masters: `xlr8_iam_module`, `xlr8_iam_process`.
- **Roles are designations**: Spatie's roles table is configured as `xlr8_admin_designation`. A user's role = their
  designation; `superadmin` is the bypass role (Gate `before` hook in `AppServiceProvider`).
- **User-level overrides**: extra direct permissions ("added") and `UserPermissionDenial` rows ("removed") checked by a
  Gate `before` hook. See `User::permissionOverrides()`.
- **Data scopes** (`xlr8_admin_user_scopes`): rows `(user_id, scope_type, scope_code)` for `branch`, `location`,
  `department`, `division`, `vertical`, `segment`, `sub_segment`, `model`, `variant`. **Applied automatically** to the
  business models (DEC-071) — see "Data scoping" below.
- `bypass_data_scoping` on a user = sees all rows; distinct from `superadmin` (P-16).

## Models
| Model | Table | Notes |
|---|---|---|
| `User` | `users` | see [core.md](../architecture/core.md) |
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

## Data scoping (DEC-071) — automatic, hierarchical, with opt-out
Every business model listed in `config/data_scope.php` `entities` **and** using `App\Models\Traits\HasDataScope` is
filtered by the signed-in user's scope on every query — admin (Backpack guard) and API (Sanctum) alike. Jobs, console
commands and imports run without a user and are never filtered. Scoped today: `CRM\{Enquiry, Lead, Campaign, Quotation}`,
`Module\Booking\{Booking, Bookingamount, XExchange, XlDelivery, XlRto, Xl_Refunds}`, `Module\Finance\XFinance`,
`Module\Insurance\XlInsurance`. Masters and pickers are **not** scoped (user decision 28-09).

**Rules** (`ScopeResolver`): no rows for a tree = everything; a parent covers all children down to the last level unless
the user holds codes at a child level; a child restriction applies within the nearest assigned ancestor
(PV + THAR → only THAR; PV + CV + THAR → THAR under PV plus every CV model); `ALL` / `ANY` rows = no restriction at that
level; only active rows inside `from_date` / `to_date` count; superadmin / `bypass_data_scoping` = everything.
Trees (`config/data_scope.php` `trees`): Branch → Location, Department → Division, Segment → Sub-segment → Model → Variant,
Vertical (flat). A child code that isn't under the assigned parent can't narrow it (e.g. division PRSNL is an ADM division).

**Filter** (`DataScopeManager`): per tree the most specific column the table has decides; an empty value falls back to the
next level up; a row empty on every level is "unassigned" and shows only while setting `scope.unassigned_rows` is
`visible` (default until backfilled). Satellites filter `via` their parent (`bid` → Booking, `enquiry_no` → Enquiry).
Master switch: setting `scope.enabled`.

| Call | Returns / does |
|---|---|
| `DataScope::current()` | `ScopeSet` of the signed-in user (unrestricted when scoping doesn't apply) |
| `DataScope::for(User $u)` / `app(ScopeResolver::class)->for($u)` | `ScopeSet` of any user |
| `ScopeSet::allowed(string $level)` | `list<string>` codes, or `null` = unrestricted; also `isUnrestricted()`, `toArray()`, `hash()` (cache keys) |
| `Model::withoutDataScope()` | one query sees every row |
| `DataScope::off(fn () => …, 'reason')` | everything inside the closure sees every row |
| route `->middleware('data-scope:off,<reason>')` | the whole request sees every row |
| `DataScope::apply(DB::table('x as b'), Booking::class, 'b')` | same filter on a raw query |
| `DataScope::enabled()`, `unassignedVisible()`, `user()` | state; `refreshSettings()` after changing `scope.*` in the same request |
| `ScopeResolver::flush(?int $userId)`, `flushMasters()` | forget memoised scopes / cached master trees (10 min) |
| `DataScopeManager::offForRequest(string $reason)` | switch scoping off for the rest of the request (what `data-scope:off` calls) |
| `DataScopeManager::applyToEloquent(Builder, Model)` / `HasDataScope::bootHasDataScope()` | internals: the global scope hands every query to the manager |
| `ScopeCodeFiller::fillBooking(Booking)` / `fillEnquiry(Enquiry, ?User)` | fill **empty** codes (enquiry / quotation links, the actor's primary branch / location, master parents); returns the columns set. Called by the models' `saving` hooks. |

```php
$bookings = Booking::query()->where('status', 1)->get();                    // already scoped
$exists = Booking::withoutDataScope()->where('quotation_id', $id)->exists(); // duplicate check across branches
$next = DataScope::off(fn () => $this->nextSequence(), 'VOTF numbering');
Cache::remember('counts:'.DataScope::current()->hash(), 60, fn () => …);    // per-scope cache key
```
Backfill existing rows: `php artisan data-scope:backfill [--entity=booking|enquiry] [--apply]` (report first).
The User edit screen shows the resolved "Effective data access".

## My Account (DEC-072) — `MyAccountService`, `Admin\Account\MyAccountController`
Routes keep Backpack's names (`backpack.account.info` GET / `.store` POST, `backpack.account.photo` POST,
`backpack.account.password` POST; Backpack's own are off: `setup_my_account_routes = false`). Only the signed-in user's
own record; no module permission (the admin panel itself admits `user_type = Emp` only — `CheckIfAdmin`).

| Method | Returns / does |
|---|---|
| `profile(User $u)` | `['user', 'person', 'employee', 'isEmployee', 'photoUrl', 'designation', 'contacts' => ['mobiles', 'emails', 'address'], 'primaries' => level => ['code', 'name'] \| null, 'addons' => level => codes beyond the primary, 'effective' => ScopeSet, 'manager' => ['name', 'designation', 'photoUrl', 'code'] \| null, 'history' => newest-first journey rows]` |
| `updateDisplayName(User $u, string $name)` | `Person` — through `PersonRecordService` (title-cased); `ValidationException` when the user has no person |
| `updatePhoto(User $u, ?UploadedFile $photo)` | `Person` — replaces the `profile_photos` image, or removes it when `$photo` is null |
| `changePassword(User $u, string $current, string $new)` | checks the current password, rejects an unchanged one, hashes, signs other sessions out; `ValidationException` on `current_password` / `new_password` |
| `photoUrl(?Person $p)` | profile photo URL or null |

The controller validates the new password as min 8 with letters and numbers (`Password::min(8)->letters()->numbers()`),
confirmed. The username is read-only. The page (`resources/views/admin/account/show.blade.php`) has tabs Profile,
Organisation & access (primary assignment, add-on scopes, effective access — shared partial
`admin.org.user._effective_access`), Employment history (timeline), Contact, Security; non-employee accounts see
Profile, Contact and Security.

## Session security (DEC-084) — `SessionGuardService`, `EnforceIdleSession`, `SecurityHeaders`
| Piece | API / behaviour |
|---|---|
| `App\Services\IAM\SessionGuardService` | `config()` → `{logout, lock, warning, lock_enabled, max_attempts}` from the `security.*` settings · `touch($session)` · `idleSeconds($session)` · `idleExpired($session)` · `idleLockDue($session)` · `isLocked($session)` · `lock($session)` · `unlock($session, $user, $password)` → Result (`WRONG_PASSWORD` with `left`, `TOO_MANY`) |
| `App\Http\Middleware\EnforceIdleSession` (Backpack `middleware_class`) | Idle expired → sign out (page: login with a notice; AJAX: 401 `IDLE_LOGOUT`). Idle lock due → lock. Locked → pages go to `xl.session.lock-screen`, AJAX 423 `SCREEN_LOCKED`. Activity = a page load or an AJAX call with `X-XL-Activity: 1`. |
| `Admin\Account\SessionLockController` | `xl.session.lock-screen` (GET), `xl.session.lock` (POST), `xl.session.unlock` (POST, throttled 10 / min, same-site `to` only), `xl.session.activity` (POST heartbeat) |
| `public/js/xl-idle.js` | Loaded by `header_metas` when a limit or the lock is on (`<meta name="xl-idle">`). It tracks keys / clicks / pointer / scroll across tabs (localStorage), sends a heartbeat at most once a minute, shows the sign-out warning, and locks / signs out on time. `[data-xl-lock]` elements lock on click. |
| `App\Http\Middleware\SecurityHeaders` (global) | nosniff, `X-Frame-Options: SAMEORIGIN`, a referrer policy, a permissions policy, HSTS on HTTPS; CSP by `security.csp_mode` (report-only by default) |

**Self-service (S5 / S7):**
- `MyAccountController::passwordRule()` is the settings-driven password rule (`account.password_*`).
- Sign-in limits are settings (N4, DEC-095 #28): the app OTP / lockout / device limits in `AuthService::limit()` read
  `security.app_*`; the admin login lockout reads `security.admin_login_*` through `AdminLoginController` (bound in
  `AppServiceProvider::register()` in place of Backpack's `LoginController`). Defaults are the previous fixed values.
- The `account.can_change_{display_name,photo,password}` switches gate both the forms and the endpoints (403, "managed
  by your administrator").

Web login: Backpack throttles 5 failed attempts per minute per username + IP (`SessionGuardTest`). There is no
persistent account lock yet (go-live to-do S4).

## AuthService (mobile OTP login, API v1)
Called by `Api\V1\AuthController`; responses are wrapped in the API envelope by the controller.

| Method | Returns / throws |
|---|---|
| `requestOtp(string $mobile, Request $r)` | `['success' => true, 'message' => …, 'data' => ['mobile', 'expires_at', 'expires_in_minutes' => 10 (+ 'otp' in local only)]]`; throws `ValidationException` (`AUTH_MOBILE_INVALID`), `AuthenticationException` (`AUTH_USER_NOT_FOUND`, `AUTH_USER_INACTIVE`), `RateLimitException` (> 5 requests / 15 min), `AccountLockedException` |
| `verifyOtp($mobile, $otp, $deviceId, $deviceName, $platform, Request $r)` | `['success' => true, 'data' => ['token', 'expires_at', 'user' => ['id', 'name', 'email', 'mobile', 'role']]]` — binds the device (`DeviceSession`), issues a Sanctum token with ability `device_id:<id>`; 5 wrong tries lock the account 30 min; throws `AuthenticationException` (`AUTH_OTP_INVALID`, `AUTH_OTP_EXPIRED`, user not found) |
| `getUserDetails(User $user)` | `['success', 'message', 'data' => ['id', 'name', 'email', 'mobile', 'role', 'permissions']]` |
| `logout(User $user)` | revokes the current token; `['success' => true, 'message' => 'Logged out successfully']` |

**Lookup and fields (DEC-095, 02-10):** the user is found by the person's primary mobile —
`User::query()->withPrimaryMobile($mobile)` (contacts store the 10-digit number; `IdentifierService::cleanMobile()`) —
and `name` / `email` / `mobile` come from `display_name` / `primary_email` / `primary_mobile` (BUG-187 fixed). The OTP
is `random_int()` (BUG-188 fixed); `xlr8_iam_otp_token.expires_at` no longer auto-updates (BUG-227). **Open:** the SMS is
a placeholder in `OtpNotificationService::sendViaSms()` — e-mail only (BUG-228). Logs carry masked numbers and never the OTP (BUG-189 fixed, DEC-070). New OTP flows should use `Sms::otp()` / `Sms::verify()`
(tech-guides/platform/11-sms.md), which are hashed, rate-limited and never logged.

## Users workbook (DEC-089 / DEC-090)
`App\Services\Org\UsersWorkbook\*` — Org → Users → Bulk import (`Export users`, `Download template`, upload).
- `UsersWorkbookColumns`: `HEADERS` (row key → the owner's exact header, in order), `MULTI` (comma-code keys),
  `SHEET = 'Users'`, `LISTS_SHEET`, `ALL`, `NONE`, `map(array $headers): array<int, key>` (loose header match).
- `UsersWorkbookMasters`: active codes per type — `names($type)` (CODE → name), `codes()`, `exists()`,
  `childrenOf($child, $parent, $parents)`, `childMap()` (location ← branch, division ← department, sub_segment ← segment,
  model ← sub_segment / segment), `employee` = active employee codes.
- `UserRowService::save(array $row, ?int $actorId = null): array{status: created|updated|failed, emp_code, messages}` —
  the one write path for the workbook and the bulk screen (W11). One transaction per row: person (`PersonService`),
  employee (`EmployeeService`, primaries rule), login (`UserService`; new = username emp code lower-case, password =
  personal mobile), designation role, person user type, scopes (`UserScopeService::sync`), history
  (`EmployeeJourneyService::recordChange`, reason designation_change / transfer / scope_change / other).
  Cells: codes (`Name (CODE)` accepted); blank keeps; multi cells = comma codes, `ALL` (= no rows, unrestricted), `NONE`
  (org add-ons: primary only; vehicle: unrestricted). Org types are stored as primary + add-ons; add-on locations /
  divisions only under the primary or add-on parents. Held / stored values are not re-checked; an unchanged cell is a
  no-op. Aadhaar: masked keeps, 12 digits replace.
  ```php
  $result = app(UserRowService::class)->save(['emp_code' => 'BMPL-0101', 'addon_branch' => 'SUJ, CHR', 'models' => 'ALL']);
  // ['status' => 'updated', 'emp_code' => 'BMPL-0101', 'messages' => []]
  ```
- `UsersWorkbookService::export(string $path, bool $withUsers = true): array{rows}` (sheets `Users`, `Lists` with named
  ranges `LST_*`, `LOC_<BRANCH>`, `DIV_<DEPT>`, `Instructions`; Aadhaar masked); `import(string $path, ?int $actorId):
  array{summary, issues, rows}`; `userRows()` (export rows; inverse of the row rules).

- `UsersWorkbookService::saveRows(array $rows, ?int $actorId, string $label = 'Row'): array{summary, issues, rows}` —
  saves rows one by one (keys = row labels); `masterPayload(): array{names, children}` — codes / names and parent → child
  maps for the screen's pickers.
- **Bulk edit screen (W11):** `Admin\Org\User\UserBulkEditController` — `GET admin/org/user/bulk` (`org.user.bulk`),
  `GET …/bulk/data` (rows JSON), `POST …/bulk` (`{rows: [...]}` ≤ 500 → `saveRows()`, results in the order sent); all
  `ORG_USER_IMPORT`. View `admin/org/user/bulk.blade.php`, script `public/js/xl-user-bulk.js` (AG-Grid 36.2.0, a
  filter-like picker editor: search, check / uncheck, `ALL` first / `NONE` last, dependent option lists; only new /
  changed / failed rows are sent, in chunks of 200; failed rows stay marked with their messages), styles `.xl-picker*`,
  `.xl-bulk-grid` in `public/css/xl-ui.css`.

## My Account — self-service personal details (DEC-091 Phase 5)
`MyAccountService::PERSONAL_FIELDS` (field → `account.can_change_*` setting), `editablePersonalFields(User): list<string>`
(switched-on fields; date of joining only for employees), `updatePersonal(User, array $input): list<string>` (changed
fields; only switched-on fields are written — person fields via `PersonRecordService`, primary e-mail / mobile via
`PersonService::upsertContact`, joining date via `EmployeeService`; blank keeps; throws `ValidationException`).
Route `backpack.account.personal` (POST `edit-account-info/personal`, error bag `personal`); the Profile tab shows the
form only when a field is on. `ui.appearance_enabled` hides the Appearance button, user-menu entry and panel.

## User reset before the new user import (owner #18, DEC-095 — W18j)
`App\Services\IAM\UserResetService` + command `users:reset` (**local only**).
- `plan(array $keepUsernames): array{ok, error, keep, remove_user_ids, remove_person_codes, counts, media_left}` —
  nothing written. Refuses an empty list, unknown usernames, or a list without an active superadmin.
- `apply(array $keepUsernames): array` (same shape) — in one transaction, permanently (base-query deletes, no events):
  - every user not kept, and its roles, direct permissions, legacy role pivot, denials, data scopes, reporting lines,
    person user types, API tokens, device sessions / tokens, OTP tokens / attempts, account locks, legacy
    `xlr8_user_branches`, chat subscriptions;
  - every employee and employee-history row except the kept users';
  - every person (with contacts, addresses, banking) not belonging to a kept user and not used by an enquiry.
  History / business rows naming a user (enquiries, audits, timeline, tasks, documents) and media files stay.
- `backupTables(): list<string>` — the 22 tables the command dumps first.
- Command: `php artisan users:reset --keep=<username> … | --keep-file=<file>` reports; add `--apply` to remove. Before
  removing it dumps those tables to `storage/app/backups/users-reset-<timestamp>.sql` (restore: `mysql <db> < file`).
  `mysqldump` comes from `--bin-dir`, else `config('database.mysql_bin_dir')` (`MYSQL_BIN_DIR`, not set in the local
  `.env` — pass `--bin-dir=D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin`), else PATH.
- Local dry run 02-10 keeping `SUP001`: 200 users, 200 employees, 214 persons, 1,501 scopes, 166 role rows would go.
- Test: `tests/Feature/IAM/UserResetServiceTest`.

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

**"Only my branches" on a new table** — add a `branch_code` (and `location_code`) column, add `use HasDataScope;` to the
model and one line to `config/data_scope.php` `entities`; no controller code. For a raw report query use
`DataScope::apply($query, Entity::class, 'alias')`.

## Gotchas
- Never mint permissions with `guard_name = 'backpack'` — they silently never match.
- Never check role names (`hasRole('GM')`) in code; check permissions.
- After changing permissions in code, clear Spatie's cache: `php artisan permission:cache-reset`.
- `$this->middleware()` in controllers doesn't exist on Laravel 11+ (BUG-159) — check inline.

## Testing
`$this->actingAs($user, 'backpack')` for admin routes; for the API use a device-bound token (see
`.ai/rules/testing.md`). Superadmin: `User::role('superadmin')->first()`; a scoped user: any active non-superadmin user
without the permission under test (see `tests/Feature/Admin/SystemSettingScreensTest`).

## My Account — Permissions & scope (owner request 30-09)
`/admin/edit-account-info` uses the dev UI kit "Pages" layout (profile header + settings card with a side menu). The
**Permissions & scope** pane (`?tab=org`) comes from `MyAccountService::access(User, $primaries, $addons)` →
`{identity {employee_code, mile_id, designation}, primary {department, division, branch, location}, addon {department,
division, branch, location: [{code, name}]}, vehicle {segment, sub_segment, model, variant}, verticals, superAdmin,
permissions {MODULE: [names]}}`. Empty parts show "—"; a blank vehicle level means unrestricted; a super admin shows
"every permission" instead of the list.

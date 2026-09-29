---
description: Auth, OTP, devices, roles/permissions and data scoping. Load for IAM, RBAC or scope work.
paths:
  - app/Models/IAM/**
  - app/Services/IAM/**
  - app/Services/RBACService.php
  - app/Services/OrgScopeService.php
  - app/Services/AuthService.php
  - app/Http/Scopes/**
  - app/Http/Controllers/Admin/Iam/**
  - app/Services/IAM/DataScope/**
  - app/Models/Traits/HasDataScope.php
  - config/data_scope.php
  - app/Providers/AppServiceProvider.php
---

# IAM & RBAC

## Model
- Spatie permission with tables prefixed `xlr8_iam_*`; **roles = designations** (`xlr8_admin_designation`,
  76 rows). `App\Models\IAM\Role` declares `$table = 'xlr8_admin_designation'` (the configured roles table) — required, or the
  guarded-attribute check re-queries information_schema per role instance (BUG-198); DEC-018.
  Manage roles and their permissions on Org → Designation (permission tree).
- Permissions `MOD_PROC_ACT`, `guard_name=web`. Never generic names (`create`, `view`).
- `User::isSuperAdmin()` = role `superadmin` → wildcard via Gate `before` hook (AppServiceProvider), which also
  applies per-user denials (`xlr8_iam_user_permission_denials`). `isSuperAdmin()` ≠ `bypass_data_scoping`.
- Dashboard needs `admin.dashboard` (granted to all designations by `GrantDashboardPermissionSeeder`, BUG-160).
- Never hardcode user ids or role names in code; check permissions.

## Data scoping
- Scope store: `App\Models\Admin\UserScope` (`xlr8_admin_user_scopes`: user_id, scope_type, scope_code, is_active).
- **Automatic (DEC-071):** business models with `HasDataScope` + a row in `config/data_scope.php` are filtered on every
  query for the signed-in user (admin + API). No rows = full access; a parent covers its children unless a child is
  assigned (within the nearest assigned ancestor). Masters / pickers are not scoped. Rows with empty codes follow
  setting `scope.unassigned_rows`; master switch `scope.enabled`.
- **Opt out only with a reason:** `Model::withoutDataScope()`, `DataScope::off(fn, 'reason')`, route `data-scope:off`.
  Required for uniqueness / numbering / duplicate checks (VOTF, receipt numbers, duplicate enquiry / booking).
- New business table: store scope **codes** (`branch_code`, `location_code`, `model_code`…), add the trait and the config
  row. Raw report queries: `DataScope::apply($q, Entity::class, 'alias')`. Per-user caches key on `DataScope::current()->hash()`.
- Jobs must not rely on a user scope (they run unscoped).
- `UserDataScope` model is deprecated (its table doesn't exist).

## Auth (API)
- OTP via `AuthService`: 6 min web / 10 min API expiry, 5 requests / 15 min, 5 failures → 30 min lock,
  max 5 devices per user. Tokens via Sanctum (`HasApiTokens` on User) bound to a device ability.
- Known debt: `AuthService` looks users up by columns `users` lacks (mobile/email) and uses `rand()` — fixed in Track B's OTP.
- Admin web auth uses the `backpack` guard; `BaseModel` stamps actors from that guard.

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
  - app/Http/Controllers/Admin/Traits/ScopedCrud.php
  - app/Providers/AppServiceProvider.php
---

# IAM & RBAC

## Model
- Spatie permission with tables prefixed `xlr8_iam_*`; **roles = designations** (`xlr8_admin_designation`,
  76 rows). `App\Models\IAM\Role` has no own table — Spatie's constructor applies the configured one (DEC-018).
  Manage roles and their permissions on Org → Designation (permission tree).
- Permissions `MOD_PROC_ACT`, `guard_name=web`. Never generic names (`create`, `view`).
- `User::isSuperAdmin()` = role `superadmin` → wildcard via Gate `before` hook (AppServiceProvider), which also
  applies per-user denials (`xlr8_iam_user_permission_denials`). `isSuperAdmin()` ≠ `bypass_data_scoping`.
- Dashboard needs `admin.dashboard` (granted to all designations by `GrantDashboardPermissionSeeder`, BUG-160).
- Never hardcode user ids or role names in code; check permissions.

## Data scoping
- Scope store: `App\Models\Admin\UserScope` (`xlr8_admin_user_scopes`: user_id, scope_type, scope_code, is_active).
- `App\Services\IAM\DataScopeService`: `null` = unrestricted, `[]` = no access (fails closed).
- `ScopedQuery` (model trait) / `ScopedCrud` (controller trait) / `DataScopeFilter` exist but enforcement is
  **not switched on** anywhere yet — turning it on is a pending user decision (BUG-136/083). Jobs must not rely on a user scope.
- `UserDataScope` model is deprecated (its table doesn't exist).

## Auth (API)
- OTP via `AuthService`: 6 min web / 10 min API expiry, 5 requests / 15 min, 5 failures → 30 min lock,
  max 5 devices per user. Tokens via Sanctum (`HasApiTokens` on User) bound to a device ability.
- Known debt: `AuthService` looks users up by columns `users` lacks (mobile/email) and uses `rand()` — fixed in Track B's OTP.
- Admin web auth uses the `backpack` guard; `BaseModel` stamps actors from that guard.

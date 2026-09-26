# Architecture essentials

- **Layers:** Controller (FormRequest validation, permission check, call one service, shape response) →
  Service (`App\Services\{Module}\{Process}\*Service`, business logic, transactions, cross-model work) →
  Model (extends `App\Models\BaseModel`: soft deletes, audit actor stamping, media, generic scopes).
- **Hierarchy:** Module → Process → Activity. Permission = `{MOD}_{PROC}_{ACT}` (e.g. `SLS_BKNG_KYC`),
  minted with `guard_name = 'web'`. Route name `module.process.activity`, URI kebab-case under
  `/admin/{module}/{process}/…`. Codes and the module table: `.ai/rules/admin-backpack.md`.
- **Authorization:** inline `if (! backpack_user()->can('CODE')) abort(403);` as the first statement of each
  admin action (see the hook-timing trap in `.ai/rules/admin-backpack.md`). API: `auth:sanctum` +
  `validate_device`; Spatie `role`/`permission` middleware aliases are registered. SuperAdmin bypass is a
  Gate `before` hook in `AppServiceProvider`. Roles **are** designations (`xlr8_admin_designation`).
- **Data scoping:** `App\Services\IAM\DataScopeService` on `xlr8_admin_user_scopes`; `ScopedQuery`/`ScopedCrud`
  exist but are not yet switched on (decision pending). Jobs must not depend on a user scope.
- **API envelope:** `{http_status, success, code, message, data}` via `BaseController` helpers.
- **Dates:** stored UTC; displayed with `site_date()` / `@sitedate` (site setting `display.date_format`).
- **Labels:** `resources/lang/en/{module}.php` is the single source for field labels & validation names.
- **Money:** new columns `DECIMAL(15,2)`; legacy varchar money is being normalised (DEC-003).
- **Every job** sets `$timeout`, `$tries`, and implements `failed()`.

# Current state (keep ≤ 50 lines; update at every checkpoint)

**Branch:** `dev/admin` (working branch). **`stage`** = `6ccaf2a` (dev/admin merged and pushed 27-09-2026, deploys to dev.xceler8.in).
**Updated:** 27-09-2026.

**Entity services (DEC-050…059), done:**
- Every data-entry entity has one write path, an `App\Support\Entity\EntityService` subclass whose `fields()` is the only rule set. It covers:
  - vehicle masters, Org masters, Person (+ contacts/addresses/banking), Employee, User, UserScope;
  - keyword masters/values;
  - pricing rules / add-ons / discounts / dealer charges / prices;
  - `VehicleService` (price-list stubs, Vehicle Info);
  - seeders.
- On update only changed values are validated. The model backstop (`HasColumnTransformations`) transforms only changed attributes and never blanks a value.
- Engine records (sessions, change flags, snapshots, history, completeness profiles) stay engine-written by design.

**Waiting on the user (left as they are, 27-09):**
- BUG-173: variant code convention; a fresh vehicle import is pending and needs `gscreds.json`.
- BUG-177: which permission gates the Imports menu / `imports/admin`.
- BUG-178: the engine ignores WIDE dealer charges and the model-scope column (price-changing fix).
- BUG-179: which accessory importer is authoritative. The accessories entity services wait on this.
- `ProductionRBACSeeder` test users are broken (DEC-059).
- Data scoping switch-on (BUG-083).
- Google service-account key rotation (history purge needs approval).

**For the booking team:**
- BUG-168: routes skipping permission checks.
- About 37 menu links with no routes.
- BUG-153: chassis status rule.
- BUG-030: XCommonHelper.

**Local data:** vehicle master tables in `xlrm` purged (DEC-051), awaiting a fresh import. Keep `xlrm_testing` as is until then; both have the DEC-055 keyword masters migrated.

**Track B:** paused after B0 (DEC-033). Resume from xceler8 `d9009db`.

**Deferred until after UAT:** Laravel 13, Excel 4, Permission 8, Firebase 8, PHPUnit 12/13, Swagger 11.

**Verification cadence:** targeted smoke per change; full suite periodically (288 passed, 1 skipped on 27-09); `--group=smoke` sweep before merges.

**Environment:** Laragon, PHP 8.4.26 (+redis), MySQL 8.4.3. After schema/data changes, run `php artisan testing:refresh-db --force --bin-dir="D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin"`.

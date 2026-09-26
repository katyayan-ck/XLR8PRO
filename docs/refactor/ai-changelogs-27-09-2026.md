# AI changelog: 27-09-2026 (Track A, UAT hardening)

Branch `feature/integrations`. Decisions DEC-033…038 are in `docs/decisions/decision-log.md`.

## UAT scope and approach (DEC-033/034)
- Track B (xceler8) is paused after B0, at `d9009db`.
- UAT covers Org/HR/User admin plus Vehicle master and Pricing. Sales belongs to the booking team and is untouched until their merge.

## User bulk import (DEC-035/036, BUG-162)
- `StandaloneUsersImport` now:
  - reads only the `Users_Import` sheet;
  - is idempotent;
  - keeps the existing `person_code` for known employees;
  - skips rows without a name;
  - returns a summary.
- `ImportUsersCommand` detects the sheet automatically. A web page (Users → Bulk import) offers a template download.
- The broken export and history pages are gone.
- Tests: `StandaloneUsersImportTest` (generated workbook) and `UserBulkImportPageTest`.

## Retired or hidden broken screens (DEC-037/038)
- **Employee, Person Address and Person Banking:**
  - create/edit URLs redirect to the lists;
  - the row action is now "Open person";
  - the employee list offers "Bulk import users".
  - Mitigates BUG-008/020/021/154.
- **Menu:** hid Vehicle → Brand (BUG-009), Sales → Reports (BUG-122), Spares (BUG-030/031/032/116) and Price List (BUG-069).
- **Brand URLs** redirect to Segment. The dead brand writers `SegmentSheet`, `MasterDataSeeder` and `CodeGenerator` are deleted.
- **Removed:**
  - the `org-demo` page, its controller and view (BUG-049);
  - the dead `vehicle/sub-segment/segments/{brandCode}` route and its commented-out JS (BUG-010/065).
- **Tracker:** BUG-011/012/016/018 verified as already fixed. The bug index was regenerated (52 open).

## Verification
- `php artisan test --compact`: 221 passed, 1 skipped.
- Smoke of all 65 parameter-free Vehicle/Pricing/Org/IAM/Utils GET screens as user 1: all 200/302 after the Brand redirect.
- User 40 (scoped): expected 403s; retired URLs return 302.

## Users & RBAC workbook (DEC-040) and user-importer hardening (BUG-163/164/165)
- **New files:**
  - `app/Services/IAM/UserRbacExportService.php` gathers the data.
  - `app/Exports/UserRbac/{UserRbacWorkbookExport,UserRbacSheet}.php` build the workbook.
  - `app/Imports/Sheets/UserScopesSheetImport.php` imports the scope rows.
  - `app/Console/Commands/ExportUserRbacCommand.php` adds `php artisan users:export-rbac [--path=]`.
  - `tests/Feature/Org/UserRbacWorkbookTest.php` (6 tests).
- **Workbook sheets:**
  - `Instructions`, `Permissions` (Module → Process → Permission), `Roles` (designations + permissions).
  - `Users_Import`: importer headers are editable; `[Read-only]` columns hold roles, effective permissions and scopes.
  - `User_Scopes`: one row per value; the value dropdown depends on the scope type.
  - Hidden `Lists`: named ranges; lists start with `ALL`.
  - Values are `Name (CODE)` labels.
- **Web (Users → Bulk import):**
  - "Export users & RBAC" (`ORG_USER_EXPORT`), route `org.user.export`.
  - "Download template" is now the same workbook without user rows (the old template had the unreadable `D.O.B.` header).
  - Import results show the User_Scopes summary.
- **`UsersImportWorkbook`:** imports `Users_Import`, then `User_Scopes` if present. `import:users` prints both summaries.
- **`StandaloneUsersImport`:**
  - Absent columns are left untouched.
  - `Employee Status`, `Employment Type` and `Login Active` are honoured.
  - No partial-name guessing (designation or org). Unknown values are reported and the stored value is kept.
  - Addon columns are merged and the slugged keys fixed (BUG-163). `dob` is read and Excel date serials parsed.
  - User type comes from the resolved designation code.
  - Reporting manager codes in any format are kept.
- **`OrgScopeService`:**
  - `vertical` added to the hierarchy.
  - Variant name column fixed (`display_name`).
  - `ALL` expansion de-duplicated.
  - `resolveLabel()` and `types()` added (BUG-164).
- **`phpstan.neon`:** the `app/Models_backup` exclude is now optional. PHPStan had been failing to start since the 26-09 purge.
- **Verification:**
  - An unchanged export re-imported on `xlrm_testing` changes no employee, user, person, contact or role. The only change is the employees' primary codes that were missing from their scopes, which are added by design (27 on the test copy).
  - Reconciliation of `storage/userdata.xlsx` against `xlrm`: 104 findings (BUG-166), in the local `storage/app/exports/userdata-vs-db-27-09-2026.xlsx`.
- **Other:**
  - BUG-055 re-verified with its wider impact documented.
  - BUG-090 impact confirmed: 34 users without a role.
  - DEC-039 (ticket intake: staff UI + API).

## Booking-team merge (DEC-041)
- `origin/stage` (booking team, 93 commits) was merged with `feature/integrations` into `stage` (`0116ec0`, pushed), then `stage` into `dev/admin` (`f34e2c5`, pushed).
- Branches deleted: `feature/integrations` (local) and `refactor/admin-permissions-formrequest-restructure` (local + origin). Both were fully contained in `stage`.
- Conflicts and resolutions are listed in DEC-041.
- Pint was not applied to the booking team's files, so their formatting is unchanged.
- Tests: 227 passed, 1 skipped.
- Smoke (user 1):
  - Sales enquiry/quotation, imports, org, vehicle, IAM and accounts screens return 200.
  - `sales/booking` returns 500 until their migrations run locally (`referee_model`).
  - `finance/import` needs local `gscreds.json`.

## After the merge: local migrations and the ID-route smoke (BUG-167/168)
- **Local DB:**
  - Took a backup of `xlr8_booking_master`, `xlr8_booking_amount`, `xlr8_crm_enquiries` and `migrations` to `storage/app/backups/` (gitignored).
  - `php artisan migrate` ran the booking team's 5 migrations plus `align_sale_type`.
  - `xlrm_testing` was refreshed. `sales/booking` now returns 200.
- **Tests:**
  - `BookingCoreServiceTest` now expects `sale_type` as an int.
  - `UserRbacWorkbookTest` scope-replacement test now creates its own precondition.
- **Smoke with real IDs:** 52 edit/details/show GET routes (Org, Vehicle, Pricing, IAM, Utils) as superadmin. Everything returned 200/302 except the settings show page (500). The branch edit URL takes the branch code (`org/branch/BKN/edit`, 200).
- **BUG-167 fixed:**
  - The settings show route was missing its `operation` key, and `show()` was ungated.
  - The key-value and keyword-master search/details routes were missing their `operation` key, so their permission check never ran.
  - The `badge` column type isn't available in free Backpack and was replaced with `text`.
  - New `tests/Feature/Admin/SystemSettingScreensTest.php` (4 tests).
- **BUG-168 recorded for the booking team:** the same route trap exists in Sales, Accounts and Spares routes.

## User decisions applied (DEC-042/043)
- **BUG-055 fixed (DEC-042):**
  - The guard-switch middleware is enabled, and `User::$guard_name = 'web'` is pinned. Without the pin, every non-superadmin permission check failed; the pre-change probe caught this.
  - The full admin smoke (169 screens, users 1 and 40) is identical before and after.
  - New `AdminAuthGuardTest`.
- **34 role-less users disabled (DEC-043):** backup taken. They now get 403 on the admin panel and are refused by OTP login.
- **Dump codes corrected (BUG-166):** `storage/userdata.xlsx` has its branch/location codes fixed, and BEV is no longer used as a division.
  - `xlrm` and `xlrm_testing`: 22 primaries and scopes set, plus the 10 scopes lost to BUG-163 added.
  - Remaining: department `IT` for 2 employees.

## Dead-code follow-up (DEC-044)
- **Removed:**
  - `VehicleAccessoryCrudController`, with its route line and 3 orphan views.
  - `Services/Exporters/UserExporter`, `Services/Importers/RulesUserImporter` and the `UserDataScope` model.
  - `DesigDeptTreeCrudController`.
  - The 4 `Employee*Assignment` models.
  - The two dead dashboard methods.
  - The pivot relations on `User`, `Employee`, `Vertical` and `Location`.
- **Fixed:** the `Location::branch()` and `Branch::primaryEmployees()` keys.
- **Tracker:** BUG-015/022/024/036/037/081/082/084/158 closed.
- **Verification:** 232 passed, 1 skipped. The full admin smoke (169 screens, users 1 and 40) is identical before and after.

## composer.json and config/app.php for PHP 8.4 (DEC-045)
- **composer.json:**
  - `php ^8.4`. The project is now named `bmpl/xceler8`, with a description and a `lint` script; stale plugin permissions were removed.
  - **Removed as unused:** `graphp/graph`, `intervention/image` (with `intervention/gif`), `spatie/laravel-translatable`, dev `laravel/sail` and `markwalet/laravel-changelog`.
  - Google services trimmed to Drive and Sheets via `Google\Task\Composer::cleanup`.
  - `composer update` brought 21 in-range updates. No vulnerabilities.
  - Majors deferred until after UAT: Laravel 13, Excel 4, Permission 8, Firebase 8, PHPUnit 12/13, Swagger 11, Tinker 3, nestedset 7.
- **Autoload:**
  - Vendor duplicates excluded from the classmap.
  - `XlInsurer` class case fixed.
  - The dead `HRJourneyServiceTest` removed.
  - `optimize-autoloader` is off locally; `deploy.yml` still optimizes.
  - Dump time is about 5s, where before it hung. BUG-034 fixed.
- **config/app.php:** every value is env-driven (`APP_TIMEZONE` default Asia/Kolkata, `faker_locale` en_IN), with notes on key rotation and multi-server maintenance mode. `.env.example` is aligned.
- **BUG-169 recorded:** mixed UTC/IST timestamps after the booking team's timezone change. Needs a decision.
- **Verification:**
  - 232 passed, 1 skipped.
  - Full smoke (169 screens, users 1 and 40) is identical.
  - The Google Sheets, Vision, Firebase, Excel and PDF classes all autoload.
- **Deploy note:** `stage`, `uat` and `main` servers must run PHP ≥ 8.4 before this merges there.
- **Also:** `bootstrap/cache/packages.php` and `services.php` (generated) and 8 stray `.tmp` files are untracked, and Laravel's standard `bootstrap/cache/.gitignore` is restored. `composer install` regenerates the caches on deploy.

## Decisions applied (DEC-046)
- **Timezone:** IST is kept; older UTC rows are not converted. BUG-169 is closed, and the architecture rule and `config/app.php` comment are updated.
- **IT department:**
  - Added through the idempotent `database/seeders/ItDepartmentSeeder.php`, applied to `xlrm` and `xlrm_testing` with a backup in `storage/app/backups`.
  - The existing `IT` division moved from Admin to become its default division.
  - BMPL-0365 and BMPL-0630 now have primary department and division `IT`, with scopes.
  - Other environments run `php artisan db:seed --class=ItDepartmentSeeder`.
- **`title_case`:** keeps business acronyms (IT, HR, PDI, CRM, LMM, RTO…) upper-case. Before, it saved "It", and editing HR/PDI in the admin would have produced "Hr"/"Pdi". New `TitleCaseAcronymTest` (8 cases).
- **Server PHP:** the user confirmed PHP 8.4 on cPanel, which clears the DEC-045 deploy gate.

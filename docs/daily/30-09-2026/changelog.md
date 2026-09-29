# Changelog — 30-09-2026

Today's changes only (the date-wise copy). The same entries are in the cumulative `docs/changelog.md` under
`## 2026-09-30`; add every new entry to **both** (`.ai/guidelines/10-workflow.md`).

## Owner-approved clean-up items (DEC-086 addendum 2): `/docs` login, dead files, BUG-211 / 212 / 213
- **`config/laradocs.php`:** `route.middleware` `['web']` → `['web', 'admin']` — guests are sent to the admin login;
  signed-in users read `tech-guides/` (BUG-211 fixed). Test: `tests/Feature/Utils/DocsSiteAccessTest.php` (2).
- **Deleted:** `app/Models/Module/Booking/XlInsurer.php` (unused duplicate, BUG-212 fixed), `app/Exceptions/Handler.php`
  (never registered; `ApiExceptionRenderer` does its job, DEC-085).
- **BUG-213 closed as not a bug:** git already tracks `app/Models/Vehicle/Pricing/Pricing.php`; the 29-09 check was fooled
  by Windows' case-insensitive paths and a broken grep escape. The class is the live price model — kept.
- **`tests/Feature/Api/ApiErrorEnvelopeTest.php`:** the exact-JSON 401 test freezes time (it failed when the clock ticked
  between the request and the assertion).
- **Bugs:** 28 open / 185 closed (BUG-211, 212, 213 moved with their details; audit rows HIGH-01 / MED-02 corrected).

## History rewrite of `dev/admin` (DEC-086 addendum 2, owner-approved)
- `git filter-branch --index-filter` over the 16 unpushed commits `4c82d28^..dev/admin` removed
  `docs/reference/XLRM-Pricing-data/` (~18 MB workbooks / PDFs) from every commit. No remote branch contained them.
- **Verified:** no commit in the range touches the path; the final tree equals the pre-rewrite tip.
- **Commit ids changed** from `4c82d28` on (e.g. `db49a10` → `a5a8c47`, `3abe70a` → `96cf3ef`, `6f98953` → `f87a013`);
  older ids quoted in these records refer to the pre-rewrite commits.
- **Backup:** local branch `backup/dev-admin-before-rewrite-30-09` (and `refs/original/…`) until the owner confirms; then
  delete them and run `git gc` to drop the objects locally.

## Merge `origin/stage` → `dev/admin` keeping our work (owner-approved, DEC-087)
- **How:** `git merge -s ours origin/stage` (records the merge; the 4 stage reverts of DEC-068…071 are not applied),
  then the team's own changes since the last revert (`51a36f6..origin/stage`, 27 files) re-applied with `git apply -3`
  and 35 conflict hunks resolved by hand.
- **Kept from the team:** enquiry list rebuilt on one base query (duplicate / lost lists, `IN_HOUSE` finance code), new
  enquiry / exchange / finance view pages (`view`, `exchange-view`, `finance-view`) with View buttons, lost-reason and
  finance-mode keyword lists, the enquiry resolved before the OTF consultant fallback, `jvAmount` on the OTF form,
  receipt rows linking to the printable receipt with the mode name, the Quotation header row on the OTF price table,
  the DO voucher date prefill, the list toolbars (reset, header customisation, Excel / PDF export) on the exchange and
  finance lists, receipt / journal-voucher, import-job, booking add / finance-edit / transaction-list, OTF PDF and menu
  changes.
- **Kept from ours where theirs would break:** `OrgService` instead of the removed `CommonHelper`; the price-hold guard
  (DEC-082); Docs-based amount proofs (DEC-069, no public temp copies); `recordEvent(action, summary, meta, body)` (their
  message is our body); site-date formatting; token colours; the pinned AG-Grid build; export libraries via `@basset`.
- **Fixed during the merge:** BUG-214 (OTF invoice date one day early in IST), BUG-215 (RTO rule match broken by the
  stage keyword change); the 3 new routes named (`sales.enquiry.duplicate`, `sales.enquiry.lost`, `sales.enquiry.view`).
- **Tests:** full suite 473 passed + the 3 RTO tests fixed by BUG-215 (1 known skip).

## Sales UI/UX pass (to-do U1 / U2 / U5; markup + CSS only, logic untouched)
- **Shared layer:** `public/css/xl-ui.css` gains the Sales grid look (`.xl-grid`), loader / popover / toolbar / export
  classes and the shared form bits; `public/js/xl-ui.js` gains `XL.notify()` (Noty toast; replaces `alert()`).
- **77 Sales views (`resources/views/admin/sales/**`):** grid containers `xl-grid`; popover, loader, search box and
  export icons on classes (inline `display:none` kept — scripts toggle it); `bg-white` / `bg-light` / `text-dark` /
  `text-black` / `table-light` → tokens; per-view `<style>` rules now covered by the shared CSS (and overrides of the
  standard card / focus / validation look) removed — 1,942 lines out; raw CDN tags (xlsx, jsPDF, autotable, jQuery mask /
  validation, SweetAlert2, Sortable, lightbox) → `@basset` with pinned URLs (SweetAlert2 via its explicit dist file);
  the new enquiry / finance / exchange view pages tokenised (no hex); the OTF anniversary picker value uses
  `site_date()` (its picker parses the site format); 13 `alert()` → `XL.notify()`.
- **Kept on purpose:** hex inside `@media print` (quotation / OTF paper sheets), the PDF-file icon SVG in booking show,
  per-screen token-based accents (coloured pinned-column headers), screen-specific layout rules (quotation sheet, bill
  tables).
- **Verified:** all Blade views compile; 82 parameter-free Sales screens rendered as user 1 (only the 8 BUG-122 report
  pages fail — missing tables, pre-existing) and user 40 (81 × 403 by permission, 0 errors); `@basset` serves the
  libraries from the local cache.

## Booking team schema (`booking.sql`) compared and aligned (DEC-088)
- **Compared** their dump (tables `xlr8_crm_booking`, `xlr8_crm_enquiries`, `xlr8_crm_quotations`, `xlr8_vehicle_variant`)
  with local `xlrm` via `information_schema` (a scratch local DB, dropped afterwards): booking and quotations identical.
- **New migration** `database/migrations/2026_09_30_013707_align_crm_enquiries_with_booking_team_schema.php` (every step
  state-checked): `cre_lost_reason`, `cre_lost_sub_reason` (VARCHAR 100 NULL, after `lost_remarks`), `vh_id` → `vh_code`,
  index `idx_mobile`, drop `x8_enq_source` only when empty (it was: 0 of 60,923 rows). `down()` fail-safe too.
- **`2026_09_24_125033_update_xlr8_crm_enquiries_table.php`:** `down()` guarded (its `up()` rename never ran — it sat
  behind a `//` comment).
- **Not aligned on purpose:** variant defaults (DEC-073) and their `model_code + code + color_code` UNIQUE index.
- **Run:** `xlrm_testing` (up → down → up) and `xlrm`; a re-diff shows only the intended differences. Schema-only backup
  taken first. Enquiry / booking / quotation / dashboard / scope tests: 109 passed.

## BUG-216 — colour mode flashing between tabs
- **`public/js/xl-theme.js`:** the cross-tab `storage` sync is debounced (150 ms) and compare-then-apply; before, it re-set
  the mode on every event and Backpack's remove + set wrote it back, bouncing between tabs (~100 flips / s).
- **Verified:** headless Chrome, two same-origin frames with Backpack's `ColorMode`: old 300 flips / 3 s, diverging; new 1
  flip per switch, converging (dark / system / light).

## W1 merge wrap-up + W2 API docs (to-do U11 complete); BUG-217
- **W1:** full suite on the merged tip — 476 passed, 1 known skip; `merge/stage-30-09` deleted (fully merged). The
  history-rewrite backup branch stays until the owner confirms.
- **W2 / U11:** new `tech-guides/api/{notifications,documents,history,webhooks}.md` and their Postman collections (the
  webhook one signs each request with a pre-request HMAC script); `tech-guides/api/index.md` — every v1 module documented.
- **BUG-217 fixed:** `NotificationController::sortFor()` allow-lists `sort_by` / `sort_order` (unknown values were a 500);
  test `tests/Feature/Api/NotificationListSortTest.php`.
- **Found, not changed:** `sender.name` / `receiver.name` are null (no `users.name`) — added to owner decision D1.

## W8 — My Account in the UI-demo layout + Permissions & scope
- **`resources/views/admin/account/show.blade.php`:** the dev UI kit "Pages" layout — profile header with a facts footer
  (employee code, OEM Mile ID, username, designation) and one settings card with a side menu (Profile, Permissions & scope,
  Employment history, Contact, Security). Forms, routes, field names and `?tab=` keys unchanged.
- **Permissions & scope** (replaces "Organisation & access", shown to every user): identity (employee code, OEM Mile ID,
  designation), primary department / division / branch / location, add-on departments / divisions / branches / locations,
  segments, sub-segments, models, variants, verticals (names, code on hover; "—" when empty), permissions grouped by module
  (accordion; super admin = every permission) and the effective data access.
- **`MyAccountService::access()`** (new) + `MyAccountController::show()` passes it; guide `tech-guides/modules/iam-auth.md`.
- **Tests:** `MyAccountTest` updated (asserts every requested field); 5 passed. Rendered for users 1 and 40.

## Continuity rule for all agents (owner request 30-09)
- **`.ai/guidelines/10-workflow.md`** (→ `CLAUDE.md` / `AGENTS.md`): new *Continuity* section — records updated on every
  task completion and every commit; mark a task in progress in the to-do and handoff before starting; keep the handoff's
  *In progress* exact at each checkpoint; a resume procedure. Change-workflow card updated.

## W9 — Vehicle Info export with master dropdowns; strict import
- **`app/Services/Vehicle/Pricing/Import/VehicleInfoWorkbookService.php`:** `addDropdowns()` — hidden `Lists` sheet, named
  ranges, list validations on Segment, Sub Segment (dependent on Segment via `INDIRECT`), Fuel, Transmission, Drivetrain,
  Body Make, Body Type, Permit, Taxi Price, Status; number ranges on Seating, Wheels, GST%. The import passes
  `mastersMustExist: true`.
- **`app/Services/Vehicle/VehicleService.php`:** `applyVehicleInfo(..., bool $mastersMustExist = false)` — unknown
  segment / sub-segment rejected instead of created; transmission / drivetrain validated against their keyword masters
  (after the variant service's own normalising, so `At` still means Automatic); new `keywordOptions()` / `keywordCode()`.
  **Before → after:** a typo in Segment used to create a new segment master; now the row is rejected with the reason.
- **Migration** `2026_09_30_023856_add_awd_to_drivetrain_keyword` (guarded, run on `xlrm` + `xlrm_testing`): `AWD` added to
  `DRIVETRAIN` (22 vehicles use it).
- **Tests:** `PricingVehicleInfoTest` +2 (strict import; dropdowns / hidden sheet / named ranges / dependent sub-segment);
  pricing suite 71 passed. Guide `tech-guides/modules/pricing.md`.

## W12 / DEC-089 Phase A — org rules
- **`app/Services/Org/BranchService.php`, `app/Services/Vehicle/SegmentService.php`:** `afterSave()` creates the same-code,
  same-name Location / Sub-segment for a new parent (Department already did it for Division).
- **Migration** `2026_09_30_024855_create_missing_same_code_org_children` (entity services, skip + log on conflicts or bad
  legacy codes such as `TESTSEG`; `down()` removes only its own rows): run on `xlrm` (no gaps) and `xlrm_testing` (CSD).
- **`app/Services/Org/EmployeeService.php`:** `checkPrimaries()` in `beforeCreate` / `beforeUpdate` — required primaries +
  vertical on create, no clearing a set value on update; blank location / division → the parent's same-code child;
  location ∈ branch, division ∈ department when either changes (DEC-054: unchanged legacy values are not re-checked).
- **Views:** `admin/org/user/{create,edit}.blade.php` — vertical required (employee users) with a required mark.
- **Tests:** new `SameCodeChildTest` (2), `EmployeePrimariesRuleTest` (4); fixtures given a vertical / primaries in
  `UserOnboardingTest`, `EmployeeUserEntityServicesTest`, `StandaloneUsersImportTest`, `UserBulkImportPageTest`; related
  suites 340 passed.
- **BUG-218 logged:** legacy employees missing primaries / vertical (data to fill).

## W10 / DEC-089 Phase B — users workbook (DEC-090)
- **New `app/Services/Org/UsersWorkbook/`:** `UsersWorkbookColumns` (the owner's 22 headers), `UsersWorkbookMasters`
  (active codes + parent → child maps), `UserRowService` (one row → person, employee, login, role, scopes, history;
  the single write path for the workbook and the W11 screen), `UsersWorkbookService` (export with dropdowns, dependent
  Primary Location / Division via `LOC_*` / `DIV_*` named ranges, Lists + Instructions sheets, masked Aadhaar; import).
- **`UserImportExportController`:** a `Users` sheet imports through the new service; Export / Template give the new
  workbook; the DEC-040 users & RBAC workbook moved to `exportRbac()` (route `org.user.export.rbac`,
  `admin/org/user/export/rbac`); older Users_Import files still import. `routes/web.php`, `admin/org/user/import.blade.php`
  (buttons + help text).
- **Before → after:** the export was the RBAC workbook (labels, one-row-per-scope sheet) → the owner's fixed layout with
  codes; `ALL` = unrestricted (no rows), `NONE` = primary only, blank keeps; unchanged cells are no-ops (DEC-090).
- **Tests:** new `tests/Feature/Org/UsersWorkbookTest.php` (7); `UserBulkImportPageTest` (template name),
  `UserRbacWorkbookTest` (route `export/rbac`); Org / IAM suites 106 passed. Round trip on `xlrm_testing`: 200 / 200 rows,
  0 failures, 0 history rows (rolled back).

## W11 / DEC-089 Phase C — bulk create / edit screen
- **New:** `app/Http/Controllers/Admin/Org/User/UserBulkEditController.php` (index / data / save, `ORG_USER_IMPORT`),
  routes `org.user.bulk`, `org.user.bulk.data`, `org.user.bulk.save` (`routes/web.php`), view
  `resources/views/admin/org/user/bulk.blade.php`, script `public/js/xl-user-bulk.js`, `.xl-picker*` / `.xl-bulk-grid`
  in `public/css/xl-ui.css`.
- **`UsersWorkbookService`:** `saveRows()` (the file import now uses it too) and `masterPayload()`.
- **Links:** "Bulk edit" on the users list (with Bulk import) and "Bulk edit on screen" on the import page.
- **Tests:** new `tests/Feature/Org/UserBulkEditTest.php` (3); with `UsersWorkbookTest` 10 passed. Grid behaviour checked
  in headless Chrome against the exported rows (picker ALL / NONE, dependent lists and resets, only edited rows sent,
  failed row kept with its message).

## W3 — Sales / booking HTTP feature tests (BUG-219, BUG-220)
- **New tests** `tests/Feature/Sales/`: `EnquiryFlowTest` (7: full create with vehicle names + `EN-` DMS prefix, required
  fields, reference source rules + redirect, virtual-call fast path, duplicate check, edit-only SC / CRE fields, create
  permission), `QuotationFlowTest` (4: revision + REVISED action only on a real change, booked stays booked BUG-096,
  history page + enquiry required, edit permission), `BookingFlowTest` (4: every booking POST / PUT / DELETE route → 403
  without Sales permissions (25 routes), create validation saves nothing, KYC identifiers + save through the service,
  DMS / OTF formats). Sales + quotation pricing + booking service suites: 77 passed.
- **Fixed BUG-220:** `QuotationCrudController::history()` — hard-coded mock customer fallback removed (file re-formatted
  by pint).
- **Logged BUG-219:** booking `store()` ignores base validation for customer type `Dummy` (owner question).

## W4 — PHPStan baseline (BUG-221)
- **New `phpstan-baseline.neon`** (level 5, all configured paths: 2 511 legacy errors — 1 230 dynamic-property reads,
  123 needless nullsafe, 54 unknown relations, 22 missing classes …) included from `phpstan.neon` with a note; the full
  `composer analyse` now reports **No errors**, so any new error fails.
- **Fixed:** `app/Exceptions/AuthorizationException.php` (`use Exception;` — a `$previous` Throwable was a TypeError) and
  `app/Models/Utilities/Settings/SystemSettingAudit.php` (`use App\Models\User;`).
- **Rule:** `.ai/guidelines/10-workflow.md` quality gate 2 — full `composer analyse` clean before merges; the baseline is
  never regenerated to hide new errors.
- **Logged BUG-221:** the remaining missing-class references (Booking helper, accessory export, spare master, RBAC seeder).

## W6 — admin flash messages from the language files (same wording)
- **55 admin controllers:** 225 flash calls (`Alert::success/error/warning/info`, `->with('success'|…)`) now read
  `__('{module}.flash.{key}', [...])`; interpolated values became `:placeholders` (e.g. `Booking #:booking_id`).
  Left as they are: `Result->message`, validator messages and variables that already hold server text.
- **Lang:** new `'flash'` groups in `resources/lang/en/{accounts,booking,iam,org,pricing,sales,vehicle}.php`; new
  `resources/lang/en/utils.php` (Utilities / imports).
- **Rule:** `.ai/rules/app.md` — admin flash wording only from the lang files or a Result.
- **Test:** new `tests/Unit/Lang/FlashMessagesLangTest.php` (every key exists, placeholders passed, wording kept).
  Full suite: 511 passed, 1 skipped. Full `phpstan analyse`: No errors.

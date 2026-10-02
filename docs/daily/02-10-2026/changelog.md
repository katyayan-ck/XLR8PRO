# Changelog — 02-10-2026

Today's changes only (the date-wise copy). The same entries are in the cumulative `docs/changelog.md` under
`## 2026-10-02`; add every new entry to **both** (`.ai/guidelines/10-workflow.md`).

## BT-005 (booking code, DEC-093) — Enquiry screens and follow-up writes through models (OTF bookings, CRE follow-ups, enquiry follow-ups, finance / exchange remarks, variant rows)
- `app/Http/Controllers/Admin/Sales/Enquiry/EnquiryCrudController.php` · `getBaseQuery()` (OTF list), `resolveVehicleFromOemCode()`, `getLatestCreFups()`, `edit()`, `showEnquiry()`, `saveCreFup()`, `exchangeEnquiryEdit()` / `View()` / `Update()`, `financeEnquiryEdit()` / `View()` / `Update()`, `showOtf()`. DEC-093. New models `CRM\OtfBooking` (`xlr8_crm_booking`), `CRM\CreFollowup` (`xlr8_cre_enquiry_fup`), `CRM\FinanceExchangeFollowup` (`xlr8_finexch_fup`); existing `CRM\EnquiryFollowup`, `Vehicle\Variant`. Reads keep every row as the raw queries did (`withTrashed()` / `withoutGlobalScopes()`, `toBase()` plain rows); the OPEN_FOLLOW_UP clean-up stays a hard delete (`withTrashed()->forceDelete()`, not a soft delete); inserts use the model's `query()->insert()` with the same columns (no model events, as before).
- Checked: enquiry list, OTF list + OTF detail, enquiry edit / view, exchange + finance edit / view, and the grid data of 9 list types (+ a search) as superadmin + user 40, with a rolled-back fixture adding CRE follow-ups and finance / exchange remarks (`tests/RouteSnapshots/enquiry.txt`, `enquiry-fixture.php`): 38 / 38 identical (the enquiry view 500s before and after — BUG-226); new `tests/Feature/Sales/EnquiryFollowupWritesTest` (CRE follow-up save with placeholder hard-delete, count and deviation; finance / exchange remark numbering) passes before and after; enquiry flow tests passed; PHPStan no new errors. Log: `docs/booking-team-changes.md`.
- **Baseline:** 271 `DB::` uses in 43 files left.

## BT-006 (booking code, DEC-093) — Journal-voucher / receipt lists and their number sequences read through the models
- `app/Http/Controllers/Admin/Accounts/JournalVoucher/JournalVoucherCrudController.php` · `index()`, `generateVoucherNumber()`; `app/Http/Controllers/Admin/Accounts/Receipt/ReceiptCrudController.php` · `index()`, `generateReceiptNumber()`. DEC-093. The customer-name lookup reads every enquiry (`Enquiry::query()->withoutGlobalScopes()`), and the number sequence keeps seeing every row of its series — soft-deleted ones and rows outside the user's data scope — under `lockForUpdate()` (`Bookingamount::query()->withoutGlobalScopes()`), so two users can never get the same number. `toBase()` keeps the plain rows.
- Checked: journal-voucher list / create / edit, receipt list / create / show / edit / browser-print, as superadmin + user 40 with a rolled-back fixture adding a receipt and a voucher (the test copy has none; `tests/RouteSnapshots/accounts-comms.txt`, `accounts-comms-fixture.php`): identical (the receipt PDF `print` differs between two runs of unchanged code — DomPDF stamps the creation time — and is checked through `browser-print`); new `tests/Feature/Sales/AccountsNumberingTest` (series continues from a soft-deleted, out-of-scope row; new series starts at 01) passes before and after; PHPStan no new errors. Log: `docs/booking-team-changes.md`.
- **Baseline:** 261 `DB::` uses in 37 files left.

## BT-007 (booking code, DEC-093) — Main enquiry list scope: `select(DB::raw(1))` → `selectRaw('1')`
- `app/Models/CRM/Enquiry.php` · `scopeMainListing()` (CRE follow-up `whereExists`). DEC-093. Same SQL: the generated query of `Enquiry::query()->mainListing()` has the same hash before and after (`… exists (select 1 from xlr8_cre_enquiry_fup …)`).
- Checked: SQL of the scope compared before / after (identical); enquiry flow tests passed; the default enquiry grid (`grid-data list_type=all`) is part of `tests/RouteSnapshots/enquiry.txt`. Log: `docs/booking-team-changes.md`.
- **Baseline:** 261 `DB::` uses in 37 files left.

## W15 — comms screens, comms webhook and the admin vehicle import off the DB facade (commit b4c51f5)
- **New model:** `App\Models\Comms\CommWebhookEvent` (`xlr8_comm_webhook_event`, no `updated_at`).
- **Converted:** `Api\CommsWebhookController` (event log insert / result update through the model, same columns),
  `Admin\Utils\Platform\CommsController` (sandbox list / record through `CommSandbox`, plain rows via `toBase()` — the
  view reads the stored payload string), `Admin\Import\AdminImportController` (key-value maps via `Keyvalue::withTrashed()->toBase()`).
- **Checked:** comms outbox list, sandbox tab and message page as superadmin + user 40 with a rolled-back fixture
  (`tests/RouteSnapshots/accounts-comms.txt`) identical; `CommsWebhookControllerTest` and the platform store tests passed.
- **Baseline:** 261 `DB::` uses in 37 files left.

## W15 — user importers, role backfill seeder, testing-DB refresh command off the DB facade
- **Converted:** `Imports\Sheets\StandaloneUsersImport` (employee / designation lookups via `Employee` / `Designation`
  `::withTrashed()` — the raw reads saw deleted rows too), `Imports\Sheets\UserScopesSheetImport` (employee / user lookups),
  `database/seeders/UserRoleBackfillSeeder` (users ⋈ employees via `User::withoutGlobalScopes()->…->toBase()`),
  `Console\Commands\RefreshTestingDatabase` (`Schema::dropDatabaseIfExists()` / `createDatabase()` — the same statements,
  charset / collation from the connection; table count via `Schema::getTables()`, which leaves views out).
- **Checked:** `StandaloneUsersImportTest`, `UserRbacWorkbookTest`, `UserBulkImportPageTest` 12 passed before and after;
  the schema grammar's drop / create statements compared; PHPStan clean.
- **Not converted, on the owner's sheet:** `EnumToKeyValueSeeder` reads `bmpl_enum_*` tables that no longer exist
  (deletion, #6); `RefreshAiContext` reads `information_schema` for the agents' schema cards (proposed exemption for schema
  tooling, #21).
- **Baseline:** 250 `DB::` uses in 33 files left.

## W15 — `data-scope:backfill` without the DB facade
- `app/Console/Commands/DataScopeBackfill.php`: enquiry / follow-up / location queries start from `Enquiry` / `EnquiryFollowup` / `Location` with scopes off and `toBase()` (so bulk updates do not add `updated_at`); raw join conditions via `whereRaw` / `orWhereRaw`; `SET e.col = emp.col` via the query builder's `raw()`; the report takes the model class.
- **Checked:** the command run on `xlrm_testing` in report and `--apply` mode inside a rolled-back transaction, before and after: console output identical and all 44 SQL statements identical in each mode (only the synonym cache's expiry time differs); PHPStan clean.
- **Baseline:** 234 `DB::` uses in 32 files left.

## W15 — 11 test files off the DB facade
- `SystemSettingScreensTest`, `SettingsInterfaceTest`, `PricingProcessStartTest`, `KeywordEntityServicesTest`, `VehicleCompareTest`, `RtoServiceTest`, `EmployeePrimariesRuleTest`, `PlatformFixtures`, `PlatformAcceptanceTest`, `UserBehaviourSettingsTest`, `ScopeCodeFillerTest`: reads through the models; deliberately legacy-shaped rows written through `Model::query()->toBase()` (no events, no entity rules — same as before).
- **Checked:** the 11 files (+ `ApprovalServiceTest`, which uses the fixtures) 68 passed / 1 skipped before and after.
- **Baseline:** 218 `DB::` uses in 21 files left.

## W15 — 9 more test files off the DB facade
- `EnquiryFlowTest`, `DataScopeFilterTest`, `InsuranceServiceTest`, `VehicleEntityServicesTest`, `ScopeResolverTest` (its `codes()` helper takes a model class), `UserBulkEditTest`, `UserBulkImportPageTest`, `OrgServiceCachingTest` (queries counted from `QueryExecuted` events instead of the connection's query log), `EmployeeUserEntityServicesTest`.
- **Checked:** 59 passed (141 assertions) before and after.
- **Baseline:** 176 `DB::` uses in 12 files left.

## W15 — last 4 test files off the DB facade
- `UserRbacWorkbookTest`, `VehicleMasterWriteTest`, `StandaloneUsersImportTest`, `UsersWorkbookTest`: every `DB::table('t' [as x])` → `Model::withoutGlobalScopes()[->from('t as x')]->toBase()` (the same raw query: no scopes, explicit filters, plain rows).
- **Checked:** 23 passed (116 assertions) before and after. All test files are now off the DB facade.
- **Baseline:** 126 `DB::` uses in 8 files left (all blocked: owner decisions or plan phase 5).

## DEC-095 — owner answers recorded (02-10)
- `docs/decisions/decision-log.md` DEC-095; `docs/owner-decisions-2026-10-01.md` status note; `docs/todo.md`: decision rows
  D1–D3, D4, D13, D14, D16, D21, D23–D26, D28, N2–N4 marked; new group **W18** (W18a–m) for the items to build.

## W18a / W18b — app OTP login, entity access, settings API (DEC-095 #1–3, #5)
- **D2 / BUG-188:** `AuthService::generateOtp()` uses `random_int()`.
- **D1 / BUG-187:** `requestOtp()` finds the user by the person's primary mobile (new scope `User::withPrimaryMobile()`);
  login / profile responses carry `display_name` / `primary_email` / `primary_mobile`; logout and the lock e-mail likewise.
  Before: every OTP request answered 500.
- **BUG-227 (found and fixed):** migration `2026_10_02_005006_fix_otp_token_expires_at_auto_update_bug227` — `expires_at`
  lost its `ON UPDATE CURRENT_TIMESTAMP` (run on `xlrm` + `xlrm_testing`).
- **D3 / BUG-182:** `ChatService::entityForApi()` — only registered entity codes / short names, record loaded through its
  model, `canView()`; used by `EntityHistoryController` (`getHistory`, `addThread`) and `DocController::upload`; document
  groups (`add`, `remove`, `zip`) need the caller's own group (or `UTL_DOCS_MANAGE`) and a visible document; `approve`
  needs `UTL_DOCS_MANAGE`.
- **BUG-207 / BUG-209:** `/api/v1/system-settings/*` behind `UTL_SETTINGS_MANAGE`; `PUT {key}` / `import/json` write through
  `SettingsService`; `BaseController::canPerform()` asks the Gate.
- **Open:** BUG-228 — the OTP SMS is a placeholder (only logged).
- **Tests:** new `AppOtpLoginTest` (2), `EntityApiAccessTest` (4); `AppSettingsApiTest` updated (+2); API suite 23 passed.
- **Docs:** `tech-guides/api/{auth,history,documents,system-settings}.md`. **App team:** use `/app-settings`; history /
  documents accept entity codes (`BOOKING`, `ENQUIRY`, …) or the short names they send today.

## BT-008 (booking code, DEC-093) — Finance view / payout edit of a booking without a finance record go back to the finance list with a message (was a 500)
- `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php` · `PayoutEdit()`, `financeView()`; `resources/lang/en/booking.php` (`flash.finance_record_missing`). BUG-223, DEC-095 #10 (owner: fix). Both pages need the booking's finance record (86 reads of it in the two views); the finance lists only link bookings that have one, so the crash came from direct links. A guard in the controller is safer than making every read null-safe (a payout must not be saved without finance).
- Checked: finance view + payout edit for one booking per status (7) as superadmin + user 40: the 4 bookings with finance render byte-identically to the committed code (superadmin's finance/5/view was re-rendered on the committed controller to confirm); the 3 without finance went 500 → 302 to `sales/booking/finance` with the message; new `BookingBugFixesTest::test_finance_pages_without_a_finance_record_return_to_the_list`; lang test passed; PHPStan no new errors. Log: `docs/booking-team-changes.md`.
- **Baseline:** 126 `DB::` uses in 8 files left.

## BT-009 (booking code, DEC-093) — Invoiced booking "View" opens the booking detail page (its view `show-invoiced` never existed)
- `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php` · `showInvoiced()`. BUG-224, DEC-095 #10. The Invoiced list's "View" link (`{id}/invoiced-show`) always answered 500. `getFullBookingData()` builds the same data for the plain `show` page, so the invoiced page uses the `show` view.
- Checked: invoiced-show / refund-view / show for one booking per status (7) as superadmin + user 40 (`tests/RouteSnapshots/bt009-010.txt`): only the invoiced booking changed (500 → 200); every other response identical; `BookingBugFixesTest::test_an_invoiced_booking_opens_from_the_invoiced_list`. Log: `docs/booking-team-changes.md`.
- **Baseline:** 126 `DB::` uses in 8 files left.

## BT-010 (booking code, DEC-093) — Refund view passes `$receiptLogs` to the booking detail view (it crashed for bookings with a refund record)
- `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php` · `refundView()`. BUG-225, DEC-095 #10. `show.blade.php` reads `$receiptLogs` as its own variable in the refund branch; `refundView()` built it only inside `$data`.
- Checked: invoiced-show / refund-view / show for one booking per status (7) as superadmin + user 40: only the refund-view of the booking with a refund changed (500 → 200); every other response identical; `BookingBugFixesTest::test_the_refund_view_opens_for_a_booking_with_a_refund`. Log: `docs/booking-team-changes.md`.
- **Baseline:** 126 `DB::` uses in 8 files left.

## BT-011 (booking code, DEC-093) — Enquiry view reads the CRE lost reason / sub-reason from the enquiry (where they are stored), not from the follow-up row
- `resources/views/admin/sales/enquiry/view.blade.php` · the CRE "Lost Reason" / "Lost Sub Reason" fields. BUG-226, DEC-095 #10. `cre_lost_reason` / `cre_lost_sub_reason` are columns of `xlr8_crm_enquiries` (the enquiry grid reads them there); `xlr8_cre_enquiry_fup` has no such columns, so the view crashed for every enquiry with a CRE follow-up.
- Checked: enquiry 60922 view / edit (with the CRE fixture), 60923 / 60920 view as superadmin + user 40: only the crashing view changed (500 → 200); new `EnquiryFollowupWritesTest::test_the_enquiry_view_opens_with_a_cre_follow_up`. Log: `docs/booking-team-changes.md`.
- **Baseline:** 126 `DB::` uses in 8 files left.

## BT-012 (booking code, DEC-093) — A Dummy booking is refused (with the first validation message, nothing saved) when the customer, branch / location, vehicle or sale type is missing
- `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php` · `store()`, after the base validator. BUG-219, DEC-095 #11 ("Dummy bookings still need the base fields"). Before, a Dummy booking skipped validation entirely and reached the insert, which failed with a database error (`del_type` cannot be null) or saved an incomplete row.
- Checked: new `BookingBugFixesTest::test_a_dummy_booking_without_its_base_fields_is_refused_and_nothing_is_saved` (failed before: SQL error); `BookingFlowTest` + `BookingBugFixesTest` 8 passed — non-Dummy bookings unchanged; payment / receipt fields stay optional for Dummy. Log: `docs/booking-team-changes.md`.
- **Baseline:** 126 `DB::` uses in 8 files left.

## BT-013 (booking code, DEC-093) — The "BEV / Personal booking submitted without a DMS SO → order 3" rule now fires: segment codes `BEV` / `PV` (were the old numeric ids 753 / 21589), and the segment is resolved from the booking, else the linked enquiry, else the segment of the model
- `app/Services/Sales/Booking/BookingDmsService.php` · `BEV_OR_PERSONAL_SEGMENTS`, `resolveEditData()`, `apply()`, `isBevOrPersonal()`, new `segmentCodeOf()`. BUG-101, DEC-095 #12 (owner: keep the rule and make it work). The booking store writes the vehicle to the enquiry, not the booking, and the ids no longer exist, so the branch never fired and the SO field never showed on the DMS form.
- Checked: DMS screens (pending-dms, pending-order, 4× dms-edit incl. `from=pending`) as superadmin + user 40: 12 of 12 identical to the committed code (test-copy bookings carry no segment); new `BookingDmsServiceTest` cases (BEV / PV without SO → 3, with SO → 2, segment from the enquiry and from the model); `BookingFlowTest` passes. Log: `docs/booking-team-changes.md`.
- **Baseline:** 126 `DB::` uses in 8 files left.

## BT-014 (booking code, DEC-093) — The hard-coded user-id lists `[5, 23, 123]` are replaced by the new permission `SLS_BKNG_ORDER_APPROVE`: Order Verification shows Accept / Reject only to its holders, and `order-update` requires it too; the two lists that did nothing are removed
- `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php` · `orderVerification()`, `orderUpdate()`, `pendingorder()`, `pendingDms()`; migration `2026_10_02_011924_add_booking_order_approve_permission_dec095.php`; `app/Services/IAM/PermissionTreeService.php` (label). BUG-095, DEC-095 #9 (D16: permission). In this database ids 5 / 23 / 123 are an Accessories Executive, a Service Cashier and a Sales Consultant without access to the page, so nobody (not even superadmin) saw the buttons; the action itself was reachable by URL for any verifier. `pendingorder()` listed `$user->id` too (always true); `pendingDms()` never used its list.
- Checked: route snapshots (order-verification, pending-order, pending-dms; users 1 + 40): only superadmin's order-verification action cell changed ("---" → Accept / Reject); user 40 and the other screens identical to the committed code; new `BookingBugFixesTest::test_order_verification_actions_need_the_approve_permission` (superadmin sees the buttons; VERIFY alone → 403; VERIFY + APPROVE → redirect); migration run on `xlrm` + `xlrm_testing`; IAM tests pass. Log: `docs/booking-team-changes.md`.
- **Baseline:** 126 `DB::` uses in 8 files left.

## BT-015 (booking code, DEC-093) — The booking menu items with no screen (Nil Payment Bookings, Dummy Bookings, Ready To Invoice, Incomplete VOTFs (@sales), RTO Agent Tracker, Brokerage) open the "coming soon" page instead of a 404 / `#`
- `resources/views/vendor/backpack/ui/inc/menu_items.blade.php` · Booking / Transactions dropdowns (the booking-module part of W18e). BUG-062, DEC-095 #8 (D13: keep the items, show "coming soon"). Part of the menu-wide change (59 items; see the changelog).
- Checked: `MenuLinksTest` (every rendered menu link has a route); dashboard + coming-soon page as superadmin and user 40 → 200. Log: `docs/booking-team-changes.md`.
- **Baseline:** 126 `DB::` uses in 8 files left.

### W18e — dead menu links open a "coming soon" page (DEC-095 #8, D13; BUG-056, BUG-062; BT-015)
- **Files:**
  - `routes/backpack/core.php`: `Route::view('coming-soon', 'admin.coming-soon')->name('coming-soon')`.
  - New `resources/views/admin/coming-soon.blade.php`.
  - `resources/lang/en/utils.php`: `coming_soon.*`.
  - `resources/views/vendor/backpack/ui/inc/menu_items.blade.php`.
  - New `tests/Feature/Admin/MenuLinksTest.php`; guide `tech-guides/platform/ui-kit.md`.
- **Before → after:** 59 rendered menu items pointed at URLs with no route (404) or `href="#"`. Each now links
  `route('coming-soon', ['feature' => '<menu label>'])`, which names the feature and links back to the dashboard. The
  items are in User Type, Approved Quotations, Booking (6, BT-015), co-dealer, CRM feedback / verifications /
  activations / alerts / concerns, Refunds, Schemes, Sales Cashier, Fee Collection and Accounts manager / cashier /
  executive. Items inside HTML / Blade comments (not shown) are untouched.
- **Why:** owner (DEC-095 #8) — keep the items, show "coming soon".
- **Checked:**
  - `MenuLinksTest`: the page escapes its input, and no rendered menu link lacks a route. It failed before with 59+
    links.
  - Dashboard + page as superadmin and user 40 → 200.
  - Lang tests pass.

### W18f — booking reports: definitions requested before the rewrite (D23, BUG-122)
- **Files:** `docs/owner-decisions-2026-10-01.md` (new W18f section, questions R1–R8), `docs/todo.md` (W18f ⏸),
  handoff. No code change.
- **Why:** the five reports compute live orders, branch columns, VIN-year blocks and booked / hot-enquiry counts from
  tables and placeholder logic that no longer exist. Rebuilding them needs the owner's definitions; the project rule is
  never to guess a business rule. The mappings that are clear are listed in the sheet.

### W18g — `DB::` guard: schema tooling exempt (DEC-095 #21)
- **Files:**
  - `tests/Unit/Architecture/NoDbFacadeQueriesTest.php`: new `EXEMPT` list with reasons.
  - `db-facade-baseline.json`: `RefreshAiContext` removed.
  - Rule text in `.ai/rules/{app,database}.md`, `.ai/guidelines/20-architecture.md`, `CLAUDE.md`, `AGENTS.md`.
- **Before → after:** `app/Console/Commands/RefreshAiContext.php` (reads `information_schema` for the schema cards; no
  model possible) sat in the W15 baseline as 4 uses to convert. It is now exempt by name, with the owner-confirmed
  reason. Baseline: 122 uses in 7 files.
- **Checked:** `tests/Unit/Architecture` passes.

### W18h — Aadhaar / PAN copies in history masked, reversibly (D26, DEC-095 #19; BUG-195)
- **Files (new):**
  - `app/Services/Platform/Privacy/KycHistoryMaskingService.php`.
  - `app/Console/Commands/MaskKycHistory.php` (`privacy:mask-kyc-history [--apply|--restore]`).
  - `app/Models/Utilities/Privacy/KycMaskBackup.php`.
  - Migration `2026_10_02_230836_create_kyc_mask_backup_table_d26.php` (`xlr8_privacy_kyc_mask_backup`; run on `xlrm` +
    `xlrm_testing`).
  - `tests/Feature/Platform/KycHistoryMaskingTest.php`.
- **Docs:** `tech-guides/platform/16-reference.md` §8; BUG-195 Fixed line.
- **Before → after:** the full numbers were copied into one booking-timeline row (`xlr8_utils_comm_thread` 27, booking
  10) and the booking change log (`audits`, written until 13-06 by the booking team's code). They are now masked by
  key: Aadhaar `XXXXXXXX1234` (also when written with spaces / dashes), PAN `XXXXXX234F`. That is 24 Aadhaar + 20 PAN
  values in 25 cells on each database.
- **Not touched:**
  - TRC / application / account numbers and GSTINs (masking is by key, never by pattern).
  - The KYC record itself (booking `adhar_no` / `pan_no`).
- **Reversible:** originals are kept encrypted per cell; `--restore` puts them back. Proven on `xlrm_testing` (apply →
  restore 25 → apply).
- **Checked:**
  - Feature test covers mask, nested JSON, other numbers untouched, idempotency, report-only, and exact restore.
  - Architecture guard and PHPStan clean.
  - Booking 10 view as superadmin and user 40 → 200.
- **Other environments:** run `php artisan privacy:mask-kyc-history --apply` on UAT / production only with the owner's
  approval.

### W18j — `users:reset`: keep only the listed accounts, remove the rest permanently (owner #18, DEC-095)
- **Files (new):**
  - `app/Services/IAM/UserResetService.php`.
  - `app/Console/Commands/ResetUsers.php` (`users:reset --keep=… | --keep-file=… [--apply] [--bin-dir=…]`).
  - `app/Models/IAM/LegacyUserBranch.php` (model for the unused legacy `xlr8_user_branches`, DEC-093).
  - `tests/Feature/IAM/UserResetServiceTest.php`.
- **Changed:** `config/database.php` adds `mysql_bin_dir` (`MYSQL_BIN_DIR`); guide `tech-guides/modules/iam-auth.md`.
- **Behaviour:**
  - Report only by default; refuses outside `local`, on an empty list, on unknown names, or on a list without an
    active superadmin.
  - `--apply` dumps the 22 affected tables to `storage/app/backups/users-reset-<ts>.sql` first.
  - It then removes, in one transaction: the other users and all their access rows, all other employees / employee
    history, and every person not kept and not used by an enquiry.
  - History rows and media files stay.
- **Checked:**
  - Tests: refusal without superadmin; kept users keep roles / scopes / employee; others gone; an enquiry's person
    stays.
  - Local dry run keeping `SUP001`.
  - The dump of the 22 tables succeeded (with `--bin-dir`).
  - PHPStan and the architecture guard are clean.
- **Not run:** waiting for the owner's account list (1 super admin, 5 dev, 1 app dev).

### W18k part 1 — sign-in limits are site settings (N4, DEC-095 #28)
- **Files:**
  - `config/platform.php`: 9 new `security.*` seeds.
  - `app/Services/AuthService.php`: the 7 OTP / lockout / device constants become `limit()` reading
    `security.app_*`.
  - New `app/Http/Controllers/Admin/Account/AdminLoginController.php`, bound in `AppServiceProvider::register()` over
    Backpack's `LoginController`; `maxAttempts()` / `decayMinutes()` read `security.admin_login_*`.
  - Tests: `tests/Feature/Admin/AdminLoginLockoutTest.php` (new), `tests/Feature/Api/AppOtpLoginTest.php` (+1).
  - Guides: `tech-guides/platform/16-reference.md`, `tech-guides/modules/iam-auth.md`.
- **Before → after:** the values were fixed in code (app: OTP 10 min, 5 requests / 15 min, 5 wrong OTPs / 15 min, 30-min
  lock, 5 devices; admin login: Backpack's 5 wrong passwords → 1-minute lock). They are now editable under Settings →
  Security, with the same defaults, so nothing changes until someone edits them.
  - Already settings before: idle logout / lock, password length / complexity, self-service e-mail / mobile / other
    field changes.
- **Checked:**
  - New tests: the admin login locks after the configured count; the OTP validity and request limit follow the
    settings.
  - API, IAM, Utils and admin-auth suites: 76 passed. PHPStan clean.

### W18k part 2 — password expiry and history as settings, off by default (N4, DEC-095 #28)
- **Files:**
  - Migration `2026_10_02_235247_add_password_expiry_and_history_n4.php`: `users.password_changed_at` and
    `xlr8_iam_password_history`; run on `xlrm` + `xlrm_testing`.
  - New `app/Models/IAM/PasswordHistory.php`.
  - `app/Services/IAM/MyAccountService.php`: `changePassword()` checks history and stamps the date; new
    `passwordExpired()`.
  - New `app/Http/Middleware/EnforcePasswordExpiry.php`, added to `config/backpack/base.php` `middleware_class`.
  - `app/Models/User.php`: cast.
  - `config/platform.php`: 2 seeds.
  - `resources/lang/en/iam.php`: `flash.password_expired`, `validation.password_recently_used`.
  - Guides `16-reference.md`, `iam-auth.md`.
  - New test `tests/Feature/IAM/PasswordPolicyTest.php`.
- **Behaviour:** both settings are 0 (off), so nothing changes until set.
  - History N refuses a password from the last N changes.
  - Expiry D days sends every admin screen to My Account (AJAX: 403 `PASSWORD_EXPIRED`) until the password is
    changed. Age counts from `password_changed_at`, else the account's creation. Never enforced when users may not
    change their password.
- **Checked:**
  - `PasswordPolicyTest` (history on / off, expiry redirect / JSON / account page open / cleared by a change).
  - IAM + Lang + architecture suites: 36 passed.
  - Dashboard, My Account and booking list → 200 as superadmin and user 40. PHPStan clean.

### W18l — `person_code` is generated, never a government ID (BUG-206, DEC-095 #15)
- **Files:**
  - `app/Services/Person/PersonRecordService.php`: `derive()` no longer copies Aadhaar / PAN / TAN into the code;
    `upsert()` without a code finds the existing person by Aadhaar → PAN → TAN (new private `findByIdentifiers()`,
    deleted ones restored).
  - Doc line in `app/Services/PersonService.php`.
  - `tests/Feature/Person/PersonEntityServicesTest.php`: the old "derived from Aadhaar" test now asserts a generated
    code; new dedupe test.
  - Guides `tech-guides/modules/person.md` and `README.md`; BUG-206 entry.
- **Before → after:** a new person's key was their Aadhaar (or PAN / TAN), spreading the ID into 14 referencing
  tables, URLs and logs. Now it is `PERS-######` (the existing fallback format). Finding the same person again works
  through the unique ID columns, so imports still update rather than duplicate.
- **Checked:** Person, Org (incl. users workbook) and user-behaviour suites: 46 passed. PHPStan clean.
- **Left:** remap the existing codes. After the user reset (W18j), only the kept accounts' persons remain; that remap
  runs with the owner's go.

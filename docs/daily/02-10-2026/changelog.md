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

# Changelog — 01-10-2026

Today's changes only (the date-wise copy). The same entries are in the cumulative `docs/changelog.md` under
`## 2026-10-01`; add every new entry to **both** (`.ai/guidelines/10-workflow.md`).

## W14 Phase 2 — vehicle content screens (DEC-092)
- **New:** `app/Services/Vehicle/Content/VehicleContentService.php`, `app/Http/Controllers/Admin/Vehicle/Content/VehicleContentController.php`,
  12 routes `vehicle.content.*` (`routes/backpack/core.php`), views `admin/vehicle/content/{index,model,trim}.blade.php`,
  menu "Vehicle Content" under Vehicles Info (`VEH_CONT_VIEW`; the dropdown also opens for it), lang `vehicle.flash.*` (5).
- **Behaviour:** specifications per model and features per trim edited in place (blank clears), new master items from
  the page, model images (many) + PDF brochure (one), trim gallery with the level choice (all colours / one colour),
  current files with Remove (ownership checked), refused files reported on the form.
- **Tests:** `tests/Feature/Vehicle/VehicleContentScreensTest.php` (6); Vehicle suite 18 passed. Smoke: superadmin 200 on
  the three pages, user 40 → 403.

## W14 Phase 3 — specifications / features workbooks (DEC-092)
- **New** `app/Services/Vehicle/Content/VehicleContentWorkbookService.php`; controller `export()` / `import()` + routes
  `vehicle.content.export`, `vehicle.content.import`; Workbooks card on `admin/vehicle/content/index.blade.php` (exports,
  import with the kind choice, match report); lang `vehicle.flash.content_imported`.
- **Formats:** ours (codes; round-trips) and the owner's sample format (by name). Dry run of the owner's samples on the
  test copy (rolled back): specifications 765 values / 54 new items / 11 model columns not matched; features 4 571 values
  / 274 new items / 115 columns not matched (names that differ from ours — listed by the report).
- **Tests:** `tests/Feature/Vehicle/VehicleContentWorkbookTest.php` (4); Vehicle suite 22 passed.

## Push checkpoint — `dev/admin` → `origin/dev/admin` (owner request 01-10, intermediate)
First push since `a22ae4c` (DEC-072): **89 commits**, fast-forward (the 30-09 history rewrite only touched unpushed
commits). Outgoing check: no workbooks, SQL, env files or blobs over 2 MB are added (the pricing reference workbooks /
docs are deletions — moved to the git-ignored `_backup/` in DEC-086). `booking.sql`, the owner's sample workbooks and
`storage/basset/.basset` (local cache map) stay out.

**What the push carries (see the dated entries above for files, before → after and tests):**
- **28–29-09 pricing (DEC-073…083):** process engine and steps 0–11, Calculate & Publish, getPricing API + price lookup,
  quotation on published prices, pricing masters kit, automatic recalculation (`30eb92e` … `b310827`).
- **29-09 platform & quality:** security (idle logout, screen lock, CSP), branded error pages, API error envelope (U7),
  form cards / density (U1–U4), API docs + Postman (U11), BUG-198 / 210; the team's enquiry / import refactors (PRs #21–#26).
- **29–30-09 repository (DEC-086 / 087 / 088):** tech-guides + records layout, date-wise records, /docs behind login,
  stage merge, enquiries schema aligned with the booking team, Sales UI pass, colour-mode fix (BUG-216).
- **30-09 users & org (DEC-089 / 090):** My Account permissions (W8), Vehicle Info dropdowns (W9), users workbook (W10),
  bulk edit screen (W11), org rules (W12).
- **30-09 quality:** Sales HTTP tests (W3; BUG-220 fixed, BUG-219 logged), PHPStan baseline (W4), flash wording in lang
  files (W6), UI clean-up outside Sales (W5), N+1 review — booking list 567 → 95 queries (W7).
- **30-09 settings (DEC-091, W13):** one categorised Settings screen (site, communication, pricing, user behaviour,
  security, modules), applied everywhere, `GET api/v1/app-settings`; BUG-207 partly fixed (secrets never returned).
- **30-09 / 01-10 vehicle content (DEC-092, W14):** data layer, content screens, specifications / features workbooks
  (Phases 1–3); compare (Phase 4) in progress.

**State at the push:** full suite 536 passed / 1 skipped (one pricing test errors only in the full run in the agent
sandbox — storage/basset not writable — and passes alone); full PHPStan clean.

## W14 Phase 4–5 — compare (DEC-092); W14 complete
- **New:** `app/Services/Vehicle/Content/CompareService.php`, `app/Http/Controllers/Admin/Vehicle/Content/VehicleCompareController.php`
  (route `vehicle.compare`, view `admin/vehicle/content/compare.blade.php`, menu "Compare Vehicles"),
  `app/Http/Controllers/Api/V1/Vehicle/CompareController.php` (routes `api.vehicles.compare.{variants,models}`),
  error codes `VEHICLE_COMPARE_SEGMENT` / `VEHICLE_COMPARE_SELECTION` (422) in `ErrorCodeEnum` + `lang/en/errors.php`,
  compare table styles in `public/css/xl-ui.css`, API docs `tech-guides/api/vehicles-compare.md` + Postman + index row.
- **Fix:** `DerivesItemCode` typed on `BaseModel` (pint had imported `Model`, which has no `withTrashed()`).
- **Tests:** `tests/Feature/Vehicle/VehicleCompareTest.php` (4); Vehicle suite 26 passed; API + lang tests 20 passed; full
  PHPStan clean. Smoke: superadmin 200 on the compare page, user 40 → 403.

## W6 remainder — no raw exception text on admin screens
- **New:** `App\Support\ErrorRef::userMessage(Throwable $e)` — business messages pass through, a validation exception gives
  its first message, SQL / PDO / PHP errors are logged with the request's `error_ref` and shown as
  `utils.flash.technical_error` ("A technical error stopped this action (reference …)").
- **Changed (17 places, 12 controllers):** error flashes / AJAX messages that appended `$e->getMessage()` — accounts
  journal voucher + receipt, IAM modules + processes, admin import, bookings (restore, RTO save, photo upload), quotation
  save / update, brand import, user delete, users workbook import, pricing insurance / RTO rule checks.
  Before: a SQL error showed the query and its values (possibly customer data); after: the reference text.
- **Guides:** `tech-guides/platform/ui-kit.md`, `.ai/rules/app.md`. **Test:** `tests/Feature/Utils/ErrorRefUserMessageTest.php` (4).

## BUG-221 (part) — accessory export works again
- **Fixed:** `app/Services/Vehicle/AccessoryExportService.php` (`php artisan vehicle-accessories:export`) — dead
  fall-back class lists → `Vehicle\{Segment,VehicleModel,Variant}`, `Core\ExportLog`; model / variant names read existing
  columns (before: `name` / `customname` on the variant table → SQL error for any scoped accessory); export log writes
  its real columns (before: `userid`, `exporttype`… silently dropped, type left at `standard_users`); failures reported.
- **PHPStan:** baseline regenerated, 2,508 → 2,500 (only removals).
- **Test:** `tests/Feature/Vehicle/AccessoryExportTest.php`.
- **Left (owner):** dead `Booking` helpers / `vehicle()` relation, `XlSpareMaster`, `ProductionRBACSeeder` (BUG-221 entry).

## DEC-093 — database access only through Eloquent (rule + guard)
- **Rule** (owner 01-10): no `DB::` queries; transaction control allowed; migrations exempt. Added to
  `.ai/rules/database.md`, `.ai/rules/app.md`, `.ai/rules/testing.md`, `.ai/guidelines/20-architecture.md` (→ CLAUDE.md /
  AGENTS.md, `.claude/rules/`).
- **Guard:** `tests/Unit/Architecture/NoDbFacadeQueriesTest.php` + `db-facade-baseline.json` (473 legacy uses in 72 files;
  ratchet — never grows, conversions lower it; comments ignored).
- **To-do:** W15 (conversion plan W15a–d).

## W15 — pricing session + vehicle content off the DB facade
- **New model:** `App\Models\Vehicle\Pricing\SessionChange` (the Discard log table; `before` cast to array, `ofSession()` scope).
- **Converted:** `PricingChangeRecorder` (log / rollback through `SessionChange` and each recorded model's query —
  `withoutGlobalScopes()->whereKey()->toBase()`, so undo still writes the stored values without events, timestamps or
  actor stamps; the recorded-model list moved from `AppServiceProvider` to `PricingChangeRecorder::MODELS`),
  `PricingImpactService` (change reads via the model; calculable-vehicles join via `Variant::withTrashed()->from()` with
  both soft-delete filters written out — same SQL), `VehicleContentService` / `VehicleContentWorkbookService`
  (`DB::raw` → `selectRaw`).
- **Guides:** `tech-guides/architecture/model-reference.md`, `tech-guides/modules/pricing.md`.
- **Tests:** Pricing + Vehicle feature suites 98 passed.
- **Baseline:** 460 uses in 68 files left.

## W15 — pricing rule testers, accessory import and pricing reset off the DB facade
- **Converted:** `RtoService`, `InsuranceService` (rule-tester reads via `RtoRule` / `InsBaseRule` / `InsIdvSlot` /
  `InsAddonRate` / `InsDefault` `->toBase()` — same plain rows; the model adds the soft-delete filter; column guards
  removed, the columns exist), `AccessoryService` (catalogue purge → `withTrashed()->forceDelete()` — same hard delete,
  no events), `PricingResetService` (model lists `FLUSH_MODELS` / `KEEP_MODELS`, `Schema::disable/enableForeignKeyConstraints()`,
  vehicle deletes via `withTrashed()->forceDelete()`).
- **Fixed on the way (BUG-222):** the reset's queue flush never ran (wrong table names); now `queue:clear` / `queue:flush` /
  `queue:prune-batches`.
- **Tests:** pricing service unit tests + reset screen + accessory model, 41 passed; Pricing + Vehicle feature suites 98.
- **Baseline:** 441 uses in 64 files left.

## W15 — platform services off the DB facade (settings overrides, comms, chat, tickets, templates)
- **New models:** `Utilities\Settings\SettingScope`, `Comms\{CommConsent,CommSuppression,CommSandbox,CommOtp}`,
  `Utilities\CommHistory\CommSubscription`, `Utilities\Ticket\TicketCounter` (plain `Model`: these tables have no soft
  deletes; `CommOtp` hides `code_hash`).
- **Converted:** `SettingsService` (scoped overrides), `ContactService` (consent / suppression via `updateOrCreate`),
  sandbox SMS / telephony drivers, `OutboxService::stats()` (`selectRaw`), `SmsService` OTP issue / verify,
  `Notify\Audience` watcher lookup, `TemplateService::recordUse()` (`increment()`), `TicketService` numbering (still
  `insertOrIgnore` + `lockForUpdate`), `ChatService` subscriptions (`firstOrCreate`).
- **Small differences (improvements):** an updated override / consent / suppression row keeps its `created_at` (the raw
  upserts reset it); a scoped override records `created_by`.
- **Tests:** new `tests/Feature/Platform/PlatformEloquentStoresTest.php` (5: OTP, consent / suppression, subscriptions,
  branch override, outbox stats); Platform feature + service unit tests 203 passed.
- **Guides:** `tech-guides/architecture/model-reference.md`.
- **Baseline:** 414 uses in 54 files left.

## W15 — Org / data-scope services off the DB facade
- **Config:** `config/data_scope.php` `trees` and `OrgScopeService::$hierarchy` name each level's master **model**
  (`'model' => Branch::class`) instead of a table.
- **Converted:** `ScopeResolver::masters()` (model query, plain rows), `ScopeCodeFiller` (variant / model / location
  lookups), `OrgScopeService` (code / name resolution, ALL expansion), `OrgService` (colour list via `Variant`;
  `getCustomerByTransactionIds()` via `Enquiry` / `Booking` with `withoutGlobalScopes()->toBase()` — the same unscoped
  rows as before; the `enq_no` OR condition is now grouped).
- **Small differences (fixes):** soft-deleted masters / variants no longer resolve codes or add colours (the raw
  queries in `OrgScopeService` and the colour list read deleted rows too).
- **Tests:** IAM + Org + Sales feature and service unit tests 210 passed.
- **Guides:** `tech-guides/architecture/core.md` (HasDataScope).
- **Baseline:** 399 uses in 50 files left.

## W15 — users / RBAC workbook export off the DB facade
- **New read models:** `IAM\RoleHasPermission`, `IAM\ModelHasRole`, `IAM\ModelHasPermission` (Spatie pivots; table
  names from `config/permission.php`; never written here).
- **Converted:** `UserRbacExportService` — 22 raw queries → model queries (aliased joins via
  `withoutGlobalScopes()->from('… as x')` with the explicit `deleted_at` filters, `toBase()` rows; `COALESCE` joins as raw
  join conditions; `DB::raw` selects → `selectRaw`); the permission-denial lookup shared as `deniedNames()`.
- **Tests:** Org feature suite (incl. `UserRbacWorkbookTest`) 33 passed; PHPStan clean.
- **Baseline:** 377 uses in 49 files left.

## DEC-094 — Help & support utility planned (W16 / W17)
- **New:** FRS `tech-guides/frs-and-workflows/frs/help-and-support-frs.md`; plan
  `tech-guides/frs-and-workflows/plans/2026-10-01-help-and-support-DEC-094.md` (+ index row); DEC-094; to-do W16a–f, W17a–d.
- Owner answers: html2canvas + Driver.js; permissions `UTL_SUPP_ADMIN` / `UTL_SUPP_EXEC`; manual screenshots via
  Playwright (dev-only); build after W15a. No code yet.

## W15 — dashboard and booking services off the DB facade
- **New models:** `CRM\EnquiryFollowup` (`xlr8_crm_enquiries_fup`), `Module\Finance\FinancerStatement`
  (`xlr8_financer_statement`).
- **Converted:** `DashboardService` (amount sums via `selectRaw(SUM(CAST …)))`; catalogue counts via the vehicle models;
  free-stock chart, open follow-ups and test drives via `Stock` / `EnquiryFollowup` / `TestDrive` with aliased joins,
  still scoped by `DataScope::apply()` on `toBase()`), `BookingExchangeService` (consultant), `BookingKycService`
  (variant / colour names), `BookingOtfService` (accessories, accessory list, trade-advance statement) — booking-team
  area, so identical rows are kept (`withTrashed()->…->toBase()`).
- **Tests:** new `DashboardTest::test_the_model_based_widgets_answer` (7 widgets); Sales + Dashboard + booking service
  tests 83 passed.
- **Baseline:** 362 uses in 45 files left.

## BT-001 (booking code, DEC-093) — Financier-statement lookups by DO number read through the `FinancerStatement` model
- `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php` · `liveNotInvoiced()` (trade-advance statement per row), `getDoAmount()`, `getTAStatement()`. DEC-093 (no `DB::` queries). New model `App\Models\Module\Finance\FinancerStatement` (table `xlr8_financer_statement`).
- Checked: OTF form (`sales/booking/otf-form`), `get-do-amount` (existing DO, unknown DO, empty), `get-ta-statement` (existing, `0`, unknown) as superadmin + user 40: 14 / 14 identical; booking tests 63 passed. Log: `docs/booking-team-changes.md`.
- **Baseline:** 359 `DB::` uses in 45 files left.

## To-do: user documentation moved last (owner 01-10)
- `docs/todo.md`: W17a–d (user manual) and new W17e (help-article content) moved to a new §13 "LAST — user
  documentation", written after all bugs are fixed and QA has vetted the functionality; W16f keeps only the developer
  guide. DEC-094 amended; plan status header updated.

## BT-002 (booking code, DEC-093) — Single-table lookups (accessory name, consultant, delivered / RTO-done ids, person / employee fallback, variant colour rows) read through their models
- `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php` · `getAccessoriesList()` (unused helper), `getConsultantDetails()`, `delivered()`, `pendingDeliveries()`, `setupUpdateOperation()` (consultant fallback), `addAmountForm()`, `pendingEdit()`, `dealerInvoice()`, `pendingRto()`. DEC-093. Models `Accessory`, `Employee` + `Person`, `XlDelivery`, `XlRto`, `Variant`. The raw queries read every row, so the model queries keep that: `withTrashed()` (soft-deleted rows included) and, for `XlDelivery` / `XlRto` (which carry the automatic data scope), `withoutGlobalScopes()`.
- Checked: delivered, delivered/list, pending-rto, pending-deliveries, otf-form, and for one booking per status (7): edit, add-amount, pending-edit, dealer-invoice, otf-form/{id} — superadmin + user 40: 80 / 80 identical; Sales tests 74 passed; PHPStan no new errors. Log: `docs/booking-team-changes.md`.
- **Baseline:** 349 `DB::` uses in 45 files left.

## W15 tooling + booking sweep findings
- **New:** `app/Console/Commands/RouteSnapshot.php` (`dev:route-snapshot`, local + `xlrm_testing` only) and
  `tests/RouteSnapshots/booking-all.txt` (every booking GET screen; one booking per status); guide
  `tech-guides/platform/15-testing.md`.
- **Full booking sweep before the booking changes** (superadmin + user 40, 484 requests): 34 failures on 17 screens, all
  pre-existing — BUG-122 (5 reports + lists: missing `xlr8_vehicle_master` / `xlr8_us_location`, owner D23), the
  chassis-number endpoint (wrong column, logged 28-09), and new **BUG-223** (finance view / payout-edit null finance),
  **BUG-224** (`invoiced-show` view missing), **BUG-225** (`refund-view` undefined `$receiptLogs`).
- **Baseline:** 349 `DB::` uses in 45 files left.

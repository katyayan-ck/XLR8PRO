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

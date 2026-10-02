<!-- Date-wise copy of .ai/state/handoff.md for 03-10-2026: kept identical to it through the day (rewrite both with every
     commit); from the next day it stays as that day's closing hand-over. -->

# Handoff — the one live state file (rewrite with every commit; `.ai/guidelines/10-workflow.md`)

**Updated:** 01-10-2026 · **Branch:** `dev/admin` · **Pushed:** 01-10 end of day (owner request) — `origin/dev/admin` = this branch;
nothing local is unpushed. Next push only when the owner asks.

## Where things are (DEC-086 layout)
- Guides: `tech-guides/README.md` (load map) · project card `tech-guides/00-project.md`.
- Records: `docs/todo.md` (Part 1 to-do, Part 2 accomplishments) · `docs/changelog.md` · `docs/bugs/open.md` /
  `closed.md` (index `.ai/state/bugs-index.md`) · date-wise `docs/daily/DD-MM-YYYY/` · `docs/decisions/decision-log.md`
  (last: DEC-092) · plans `tech-guides/frs-and-workflows/plans/` (all current plans closed ✅).
- Superseded files: `_backup/` (git-ignored; never read by agents).

## Project state (summary; details in `docs/todo.md` Part 2)
- **On `stage`:** entity services (DEC-050…059), platform utilities (DEC-060…065), UI layer (DEC-066…069), data scoping
  (DEC-071).
- **On `origin/dev/admin` (pushed 01-10):** bug-fix wave (DEC-070), My Account + dashboard (DEC-072), pricing redesign +
  masters (DEC-073…083), security baseline (DEC-084), API error envelope (DEC-085), repo clean-up (DEC-086), stage merge
  + booking-team schema (DEC-087/088), users workbook + bulk edit + org rules (DEC-089/090, W10–W12), W1–W9, Sales tests
  (W3), PHPStan baseline (W4), UI clean-up (W5), flash messages in lang files (W6), N+1 review (W7), one categorised
  Settings screen (DEC-091, W13), vehicle content Phases 1–3 (DEC-092).
- **Local only:** `8cfd223` W14 Phase 4–5 (compare screen + API) and the records commits after it.
- **Local data:** vehicle masters in `xlrm` purged (DEC-051) awaiting a fresh import; no published price snapshots
  locally. Mobile OTP login is broken until D1 (BUG-187).
- **Track B:** paused after B0 (resume from xceler8 `d9009db`). Deferred until after UAT: Laravel 13, Excel 4,
  Permission 8, Firebase 8, PHPUnit 12/13, Swagger 11.

## Just done (latest first; older days in `docs/daily/`)
- 01-10: BT-015 (booking code) — The booking menu items with no screen (Nil Payment Bookings, Dummy Bookings, Ready To Invoice, Incomplete VOTFs (@sales), RTO Agent Tracker, Brokerage) open the "coming soon" page instead of a 404 / `#`.
- 01-10: BT-014 (booking code) — The hard-coded user-id lists `[5, 23, 123]` are replaced by the new permission `SLS_BKNG_ORDER_APPROVE`: Order Verification shows Accept / Reject only to its holders, and `order-update` requires it too; the two lists that did nothing are removed.
- 01-10: BT-013 (booking code) — The "BEV / Personal booking submitted without a DMS SO → order 3" rule now fires: segment codes `BEV` / `PV` (were the old numeric ids 753 / 21589), and the segment is resolved from the booking, else the linked enquiry, else the segment of the model.
- 01-10: BT-012 (booking code) — A Dummy booking is refused (with the first validation message, nothing saved) when the customer, branch / location, vehicle or sale type is missing.
- 01-10: BT-011 (booking code) — Enquiry view reads the CRE lost reason / sub-reason from the enquiry (where they are stored), not from the follow-up row.
- 01-10: BT-010 (booking code) — Refund view passes `$receiptLogs` to the booking detail view (it crashed for bookings with a refund record).
- 01-10: BT-009 (booking code) — Invoiced booking "View" opens the booking detail page (its view `show-invoiced` never existed).
- 01-10: BT-008 (booking code) — Finance view / payout edit of a booking without a finance record go back to the finance list with a message (was a 500).
- 02-10: W18a / W18b — app OTP login fixed (BUG-187 / 188 / 227), app API record access (BUG-182), settings API managers only (BUG-207 / 209); BUG-228 logged.
- 02-10: owner answers recorded (DEC-095); to-do W18 opened.
- 01-10: BT-007 (booking code) — Main enquiry list scope: `select(DB::raw(1))` → `selectRaw('1')`.
- 01-10: BT-006 (booking code) — Journal-voucher / receipt lists and their number sequences read through the models.
- 01-10: BT-005 (booking code) — Enquiry screens and follow-up writes through models (OTF bookings, CRE follow-ups, enquiry follow-ups, finance / exchange remarks, variant rows).
- 01-10: BT-004 (booking code) — Quotation screens read segment / model / variant / colour names and the booking map through the models.
- 01-10 end of day: BT-003 booking grids, execution plan (phases 0–10), owner decision sheet (37 items), records; pushed. Baseline 314 / 45.
- 01-10: BT-003 (booking code) — Booking grid queries without the DB facade: enquiry-reference join as one helper, computed columns via selectRaw, counts / names through the models.
- 01-10: BT-002 (booking code) — Single-table lookups (accessory name, consultant, delivered / RTO-done ids, person / employee fallback, variant colour rows) read through their models.
- 01-10: user documentation (W17 manual + help-article content) moved to the end of the to-do list (owner).
- 01-10: BT-001 (booking code) — Financier-statement lookups by DO number read through the `FinancerStatement` model.
- 01-10: W15 — dashboard and booking services off the DB facade (baseline 362 / 45).
- 01-10: DEC-094 — help & support utility planned (FRS, plan, to-do W16 / W17); build after W15a.
- 01-10: W15 — users / RBAC workbook export off the DB facade (baseline 377 / 49).
- 01-10: W15 — Org / data-scope services off the DB facade (baseline 399 / 50).
- 01-10: W15 — platform services off the DB facade (settings overrides, comms, chat, tickets, templates) (baseline 414 / 54).
- 01-10: W15 — pricing rule testers, accessory import and pricing reset off the DB facade (baseline 441 / 64).
- 01-10: W15 — pricing session + vehicle content off the DB facade (baseline 460 / 68).
- 01-10: DEC-093 — rule "no `DB::` queries, Eloquent only" + guard test with a shrinking baseline; W15 opened.
- 01-10: BUG-221 part — accessory export repaired (SQL error on scoped rows, wrong export-log columns); PHPStan baseline 2,500.
- 01-10: W6 remainder — 17 admin error messages no longer show raw exception text (`ErrorRef::userMessage()`).
- 01-10: records tidy-up — this handoff rewritten (stale "next" items removed), to-do / bug index verified.
- 01-10: W14 complete (DEC-092) — Phase 4–5 compare (`CompareService`, Vehicles → Compare Vehicles,
  `GET api/v1/vehicles/compare/{variants,models}` + docs / Postman); Phase 3 workbooks (ours by code, OEM samples by name
  with a match report); Phase 2 Vehicle Content screens.
- 01-10: pushed `dev/admin` → `origin/dev/admin` (89 commits, fast-forward).
- 30-09: W14 Phase 1, W13 (Phases 1–6 + W13g), W7, W5, W6, W4, W3, W10–W12, W8, W9, W1, W2, BUG-216, DEC-087/088 — see
  `docs/daily/30-09-2026/`.

## In progress / next
- **W18 (DEC-095) — building the owner's 02-10 answers, in order W18a → W18m.** Booking items are numbered BT changes
  (`docs/booking-team-changes.md`), each checked with `dev:route-snapshot` / tests before and after, each revertable.
  Done: W18a, W18b, W18c (BT-008 … BT-013), W18d (BT-014: `SLS_BKNG_ORDER_APPROVE`, granted to no designation yet;
  BUG-229 logged — owner: what should Reject do), W18e (coming-soon page, 59 menu items, BT-015).
  **W18f ⏸** — the 5 booking reports need the owner's definitions (questions R1–R8 in
  `docs/owner-decisions-2026-10-01.md`, W18f section; code at `BookingCrudController` ~l.8932 stockReport, ~9153
  liveOrderReport, ~9219 fetchCbrData, ~9612 consolidatedBookingReport, ~9888 branchBookingReport, ~10134
  pendingActionsReport; stock = `Stock` model `xlr8_booking_stock_master`, `location_id` = `xlr8_admin_location.id`).
  W18g ✅ (guard `EXEMPT` list: `RefreshAiContext`; baseline 7 files).
  W18h ✅ (`privacy:mask-kyc-history`, applied locally on both DBs: 25 cells; UAT / prod run needs approval).
  W18i ⏸ (D25 variant codes + purge / re-import go with the pricing run — owner: pricing later).
  W18j ✅ built (`users:reset`, local only; run when the owner sends the account list; use
  `--bin-dir=D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin`).
  W18k part 1 ✅ (sign-in / OTP / lockout / device limits as `security.*` settings; admin login via
  `AdminLoginController` bound over Backpack's).
  W18k ✅ (part 2: `account.password_expiry_days` / `account.password_history_count`, off by default;
  `EnforcePasswordExpiry` middleware; `xlr8_iam_password_history`).
  W18l 🟡 — new persons get `PERS-######`, upsert finds a person by Aadhaar → PAN → TAN (done). The remap of the
  existing codes waits for the user reset (W18j): afterwards only the kept accounts' persons remain → small remap with
  an old → new map, owner's go.
  W18m 🟡 — Redis ready (`.env` switch only, guide 16-reference §4; BUG-230 queue `retry_after` 1900 s fixed).
  **Owner 03-10:** Playwright approved; config + `tests/E2E/smoke.spec.ts` written. Owner runs the install
  (`npm i -D @playwright/test`, `npx playwright install chromium`); then
  `E2E_USER=… E2E_PASSWORD=… npx playwright test`. KYC masking on UAT / production approved; the owner adds
  `php artisan privacy:mask-kyc-history --apply || echo "KYC masking failed"` to `deploy-hook.sh` (agent edits to the
  deploy hook and the npm install were blocked by the tool's permission guard — do not retry them).
  **Owner deals later:** W18f report definitions (R1–R8), BUG-229 Reject rule, `users:reset` account list.
- **W16 help & support (DEC-094):** W16b ✅, W16c ✅ 03-10 (pane, Help centre, tours, "new" dot; guide
  `tech-guides/platform/17-help-support.md`). **Next step: W16d** — diagnostics collector: `public/js/xl-diag.js` ring
  buffer in sessionStorage per tab (last 50 page loads / clicks with label + selector, form submits with field
  **names** only, fetch / XHR / jQuery AJAX method-URL-status-time, JS errors, alerts shown — never typed values);
  server request trail middleware (cache, 50 per user, 2 h: route, status, time, error ref); html2canvas (approved in
  DEC-094, via Basset, loaded on demand) screenshot with password / OTP / `data-xl-sensitive` fields blanked + preview;
  tests for the trail + masking helpers. FRS §5.2 lists the bundle files.

- **W15 (DEC-093) — `DB::` → Eloquent, now including the booking team's code** (126 uses / 8 files left).
  Done: rule + guard; pricing, vehicle content, platform, Org / data scope, RBAC export, dashboard, booking services;
  booking code **BT-001 … BT-007** (numbered, one commit each, logged in `docs/booking-team-changes.md`: where, what, why,
  before → after, checked, revert).
  **Unblocked part done (02-10):** booking BT-001…007, platform, console, importers, all tests; full suite 573 passed.
  **Left (122 uses / 7 files, all blocked):** `Booking` model dead helpers + `EnumToKeyValueSeeder` + uncalled booking
  helpers (deletion #6), `BrandCrudController` (D5), booking reports (D23), `SpareRequestCrudController` (D28),
  `ImportEnquiriesJob` + `SalesImportController` (phase 5). (`RefreshAiContext` exempt, W18g.) Resume each when its answer comes.
  **Per change:** `APP_DEBUG=false DB_DATABASE=xlrm_testing php artisan dev:route-snapshot 1,40 <spec> before.json [--setup=fixture.php]` on the unchanged
  code → edit → pint the file (only sorts imports) → `php -l` → phpstan (no new errors) → the same command with
  `--compare=before.json` (must report 0 differences) → Sales tests → lower the baseline
  (`UPDATE_DB_FACADE_BASELINE=1 php artisan test --compact tests/Unit/Architecture`) → log entry → commit.
  Keep behaviour identical: raw reads saw soft-deleted / out-of-scope rows → `withTrashed()` / `withoutGlobalScopes()`;
  plain row objects → `->toBase()`.
- **W16 (DEC-094) — help & support mechanism — NEXT** (F1 pane, tours, support requests; owner 01-10: build after W15's
  unblocked part, which is done). Start with W16b (help engine + F1 pane) per
  `tech-guides/frs-and-workflows/plans/2026-10-01-help-and-support-DEC-094.md`. **User manual + help
  texts (W17) come last** (to-do §13 — after bugs are fixed and QA has vetted; owner 01-10).
- **Execution order:** `tech-guides/frs-and-workflows/plans/2026-10-01-execution-order.md` (phases 0–10, proposed 01-10;
  decisions listed to the owner). Until the owner answers, continue with work that is not blocked (BT-003 grid helpers).

## Waiting on the owner
- **Answer sheet:** `docs/owner-decisions-2026-10-01.md` (37 items, phases 1–9) — read the answers before starting each phase.
- **DEC-093 defaults:** transaction control (`DB::transaction`) stays allowed and migrations are exempt — confirm.
- **Push / merge:** merge `dev/admin` into `stage` (N2); delete
  `backup/dev-admin-before-rewrite-30-09` + `git gc`.
- **W14:** grant `VEH_CONT_VIEW` / `VEH_CONT_EDIT` / `VEH_CMPR_VIEW` to the designations that need them (only superadmin
  has them now); load the sample workbooks on UAT (Vehicle Content → Workbooks) and fix the unmatched names the report
  lists (test copy: 11 model / 115 trim columns).
- **W13 deploy:** set Settings → Site → Dealership name on UAT (a demo value such as "ABC Motors" would show in the
  header). Delete the unused `resources/views/admin/pricing/{hold,tcs}/index.blade.php`?
- **Environment:** `CACHE_STORE=database` makes every cache read a query (~20–30 per page after W7); Redis or file cache
  on UAT recommended.
- **BUG-221 deletions:** OK to delete the Booking model's dead `vehicle()` relation + nine dashboard helpers
  (`getDynamicBookingCounts()` … `getBookingsOlderThan()`, no callers, removed tables)? `XlSpareMaster` / `ProductionRBACSeeder` go
  with D5–D12.
- **Booking bugs found 01-10 (booking team / owner):** BUG-223 finance view + payout-edit 500 without a finance record;
  BUG-224 `invoiced-show` view missing; BUG-225 `refund-view` undefined `$receiptLogs` — fix with the booking team or
  approve the agent fixing them.
- **BUG-228:** SMS vendor / DLT details so the app OTP is really sent by SMS.
- **App team:** use `/app-settings`; history / documents take entity codes.
- **Bugs:** BUG-219 (should a `Dummy` booking still need the base fields?); BUG-207 remainder (narrow or retire
  `GET system-settings` / `topic` / `category` / `{key}`; move PUT / import onto SettingsService); BUG-218 (HR fills the
  missing employee primaries).
- **DEC-090 check:** workbook `ALL` = unrestricted (also covers codes added later); `NONE` on an org add-on = primary
  only. Say if `ALL` should mean today's codes only.
- **Decisions:** D1–D3, D13, D23, D29 (P0); deletions D5–D12; data dictionary (5 questions); security policy N4 (idle
  minutes, lock, password expiry / history, email / mobile self-service, token expiry).
- **Package approvals:** 2FA, backups, error tracking, browser tests. **API:** `E002` rename (app team); BUG-209.

## How to verify
- `php artisan test --compact` (~5 min): **573 passed, 1 known skip, 0 failures on 02-10** (`PricingRecalcTest` has
  errored inside the full run in this sandbox before and passes alone). Tests run on `xlrm_testing`; migrate the copy with
  `DB_DATABASE=xlrm_testing php artisan migrate` (never `testing:refresh-db`). Do **not** set `BASSET_CACHE_MAP=false`
  for the normal suite (10× slower); use it only for `php artisan test --group=smoke` here (storage/basset not writable).
- Vehicle content: `php artisan test --compact tests/Feature/Vehicle` (27 passed).
- Local MySQL (Laragon) must be running.
- After editing `.ai/`: `php artisan ai:refresh-context`, `php artisan boost:update`, then copy `AGENTS.md` to
  `CLAUDE.md` (Boost only rewrites `AGENTS.md`).

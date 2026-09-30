<!-- Date-wise copy of .ai/state/handoff.md for 01-10-2026: kept identical to it through the day (rewrite both with every
     commit); from the next day it stays as that day's closing hand-over. -->

# Handoff — the one live state file (rewrite with every commit; `.ai/guidelines/10-workflow.md`)

**Updated:** 01-10-2026 · **Branch:** `dev/admin` · **Pushed:** up to `7140141` (01-10, owner request); later commits
are local only — pushing again needs the owner's approval in that turn. Not merged to `stage` (N2, owner).

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
- **W15 (DEC-093) — convert `DB::` queries to Eloquent** (460 uses / 68 files left). Done: rule + guard; pricing session + vehicle content.
  Next step: W15a continued — `app/Services/Vehicle/Pricing/{RtoService,InsuranceService,PricingResetService}.php`, `AccessoryService`, then Platform services (Settings, Comms, Chat, Ticket, Templates, Notify), Org / IAM services, Dashboard, Booking services, jobs, console, imports, models.
  Method: model per table (new models for tables without one), behaviour identical, run the file's tests, then lower
  the baseline: `UPDATE_DB_FACADE_BASELINE=1 php artisan test --compact tests/Unit/Architecture/NoDbFacadeQueriesTest.php`.
- Before W15: Every to-do row W1–W14 is ✅; the remaining 🟡 rows (S4/S5/S7/S9/S12/S14, SL6, O8, DA4, Q*, U2,
  F3, N2) each wait on an owner decision, a package approval or are "converge when touched".
- **Unblocked follow-ups, in order:**
  - None left without the owner (BUG-221's remainder is a deletion — see *Waiting on the owner*).

## Waiting on the owner
- **DEC-093 defaults:** transaction control (`DB::transaction`) stays allowed and migrations are exempt — confirm.
- **Push / merge:** push the local commits after `7140141`; merge `dev/admin` into `stage` (N2); delete
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
- **Bugs:** BUG-219 (should a `Dummy` booking still need the base fields?); BUG-207 remainder (narrow or retire
  `GET system-settings` / `topic` / `category` / `{key}`; move PUT / import onto SettingsService); BUG-218 (HR fills the
  missing employee primaries).
- **DEC-090 check:** workbook `ALL` = unrestricted (also covers codes added later); `NONE` on an org add-on = primary
  only. Say if `ALL` should mean today's codes only.
- **Decisions:** D1–D3, D13, D23, D29 (P0); deletions D5–D12; data dictionary (5 questions); security policy N4 (idle
  minutes, lock, password expiry / history, email / mobile self-service, token expiry).
- **Package approvals:** 2FA, backups, error tracking, browser tests. **API:** `E002` rename (app team); BUG-209.

## How to verify
- `php artisan test --compact` (~4 min): **561 passed, 1 known skip, 0 failures on 01-10** (`PricingRecalcTest` has
  errored inside the full run in this sandbox before and passes alone). Tests run on `xlrm_testing`; migrate the copy with
  `DB_DATABASE=xlrm_testing php artisan migrate` (never `testing:refresh-db`). Do **not** set `BASSET_CACHE_MAP=false`
  for the normal suite (10× slower); use it only for `php artisan test --group=smoke` here (storage/basset not writable).
- Vehicle content: `php artisan test --compact tests/Feature/Vehicle` (27 passed).
- Local MySQL (Laragon) must be running.
- After editing `.ai/`: `php artisan ai:refresh-context`, `php artisan boost:update`, then copy `AGENTS.md` to
  `CLAUDE.md` (Boost only rewrites `AGENTS.md`).

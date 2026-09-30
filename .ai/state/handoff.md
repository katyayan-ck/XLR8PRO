# Handoff — the one live state file (rewrite with every commit; `.ai/guidelines/10-workflow.md`)

**Updated:** 30-09-2026 · **Branch:** `dev/admin` · **Pushed:** no. The next stage merge waits on the owner
(origin/stage has 4 reverts by the booking team).

## Where things are (DEC-086 layout)
- Guides: `tech-guides/README.md` (load map) · project card `tech-guides/00-project.md`.
- Records: `docs/todo.md` (to-do + accomplishments) · `docs/changelog.md` · `docs/bugs/open.md` / `closed.md` ·
  date-wise `docs/daily/DD-MM-YYYY/` ·
  `docs/decisions/decision-log.md` · plans `tech-guides/frs-and-workflows/plans/`.
- Superseded files: `_backup/` (git-ignored; never read by agents).

## Project state (summary; details in `docs/todo.md` Part 2)
- **Done and on `stage`:** entity services (DEC-050…059), platform utilities (DEC-060…065), UI layer / Appearance /
  AG-Grid theming (DEC-066…069), data scoping (DEC-071).
- **Done on `dev/admin`, not pushed:** bug-fix wave (DEC-070), My Account + dashboard (DEC-072), pricing redesign +
  masters (DEC-073…083), security baseline (DEC-084), API error envelope (DEC-085), error pages, form cards + density,
  API docs (pricing, settings, auth, devices), BUG-198 / 208 / 210 fixes, this clean-up (DEC-086).
- **Local data:** vehicle masters in `xlrm` purged (DEC-051) awaiting a fresh import; no published price snapshots
  locally (run the Pricing Process to fill them). Mobile OTP login is broken until D1 (BUG-187).
- **Track B:** paused after B0 (resume from xceler8 `d9009db`). Deferred until after UAT: Laravel 13, Excel 4,
  Permission 8, Firebase 8, PHPUnit 12/13, Swagger 11.

## Just done (latest first)
- 30-09: W13 settings interface added to the to-do; owner answered (DEC-091: UTL_SETTINGS_MANAGE / PRC_WKFL_MANAGE, hold in Settings + process, encrypted secrets); plan saved. Next after W5 / W7 or on request: W13 Phase 1 (catalogue `config/settings_ui.php` + tabbed screen).
- 30-09: W6 — admin flash messages from `resources/lang/en/{module}.php` 'flash' groups (+ new `utils.php`), same wording.
- 30-09: W4 — `phpstan-baseline.neon` (2 511 legacy errors), full analyse clean, 2 missing imports fixed, BUG-221 logged.
- 30-09: W3 — Sales HTTP feature tests (`tests/Feature/Sales/`), BUG-220 fixed, BUG-219 logged.
- 30-09: W11 / DEC-089 Phase C — Org → Users → Bulk edit (`UserBulkEditController`, `xl-user-bulk.js`, picker editor); DEC-089 plan closed.
- 30-09: W10 / DEC-089 Phase B — users workbook (`app/Services/Org/UsersWorkbook/`, export / template / import; RBAC workbook at `export/rbac`); DEC-090 cell semantics. Round trip 200/200 no-op.
- 30-09: W12 / DEC-089 Phase A — same-code children (services + migration), employee primaries + vertical enforced (`EmployeeService::checkPrimaries()`), BUG-218 logged.
- 30-09: W9 — Vehicle Info export with master dropdowns (hidden Lists sheet, dependent sub-segment) and a strict import; `AWD` added to DRIVETRAIN.
- 30-09: Continuity rule added for all agents (`.ai/guidelines/10-workflow.md`).
- 30-09: W8 — My Account in the UI-demo layout with Permissions & scope (`MyAccountService::access()`). Next: W9 Vehicle Info export dropdowns.
- 30-09: W1 (merge wrap-up: 476 passed, temp branch deleted) and W2 (last API docs + Postman; BUG-217 fixed). Next: W3 Sales / booking feature tests.
- 30-09: BUG-216 fixed — colour mode flashed between open tabs (cross-tab sync loop in `xl-theme.js`).
- 30-09: booking team schema (`booking.sql`) compared; fail-safe migration `2026_09_30_013707_align_crm_enquiries_with_booking_team_schema` run on `xlrm` + `xlrm_testing` (DEC-088).
- 30-09: Sales UI/UX pass — 77 views on the shared layer (xl-grid, toolbar / popover / loader classes, tokens, @basset, XL.notify); logic untouched.
- 30-09: `origin/stage` merged into `dev/admin` keeping DEC-068…071 and the team's 27-file work (DEC-087); BUG-214 / 215 fixed on the way.
- 30-09: `dev/admin` history rewritten to drop the pricing workbooks (backup branch `backup/dev-admin-before-rewrite-30-09`).
- 30-09: `/docs` behind the admin login (BUG-211); deleted the unused `Booking\XlInsurer` (BUG-212) and `Exceptions\Handler`; BUG-213 closed as a false positive (the file is `Pricing.php`, the live model).
- Date-wise records: `docs/daily/29-09-2026/{handoff,changelog,accomplishments}.md`, kept in step with the cumulative files (rule in `10-workflow.md`).
- **DEC-086 clean-up (to-do 10c C1–C7):** `tech-guides/` (architecture, modules with cards, platform, api,
  frs-and-workflows with FRS, plans and workflow cards); `docs/` reduced to records; bugs verified and split (31 open /
  182 closed, BUG-029 closed, BUG-211…213 new); one changelog; to-do + accomplishments merged; `_backup/`; `CLAUDE.md` /
  `AGENTS.md` 21.1 → 12.4 KB (generic Boost sections excluded via `config/boost.php`), unused skills removed, stale
  pricing skill rewritten, `.claude/settings.json` read-denies; Laradocs reads `tech-guides/`.
- U11: auth + devices API docs; BUG-210 fixed (device push-token registration).
- U7 (API side, DEC-085): one error envelope for every `api/*` exception; messages in `resources/lang/en/errors.php`.
- U1 / U3 / U4: collapsible + draggable form cards with required badges, density settings, lazy images.

## In progress / next
0. **W10–W12 (DEC-089) ✅ done** — plan closed; users workbook + bulk edit screen + org rules; legacy gaps BUG-218 (data, owner / HR). W3 ✅ (15 Sales HTTP tests; BUG-219 open, BUG-220 fixed). W4 ✅ (baseline, full analyse clean; BUG-221). W6 ✅ (225 flash calls → lang `flash` groups; FlashMessagesLangTest). Next step: W5 (UI clean-up outside Sales: hex / inline styles → shared layer, same method as the Sales pass), then W7 (N+1 review). Then W4 (PHPStan baseline), W5, W6, W7.
1. **U11 API docs:** notifications / alerts / messages, documents, history, webhooks (`tech-guides/api/`).
2. **U7 web side:** admin flashes through the same codes / language file.
3. **U1 per screen:** header-less Backpack form cards get headers when converted; a real-browser check of quotation /
   booking forms.

## Waiting on the owner
- BUG-219: should a `Dummy` booking still need the base fields (name, mobile, branch, vehicle, sale type)? Today they are skipped.
- DEC-090 (agent, within DEC-089): workbook `ALL` = unrestricted (no scope rows, so it also covers codes added later); `NONE` on an org add-on = primary only; a new employee must list vertical codes (no `ALL`). Say if `ALL` should mean today's codes only.
- **Rewrite backup:** delete `backup/dev-admin-before-rewrite-30-09` + `git gc` when confirmed.
- **Deletions:** D5–D12.
- **Data dictionary:** 5 questions; **P0 decisions:** D1–D3, D13, D23, D29, N2 (stage merge).
- **Security policy (N4):** idle minutes, lock, password expiry / history, email / mobile self-service, token expiry.
- **Package approvals:** 2FA, backups, error tracking, browser tests. **API:** `E002` rename (app team);
  BUG-207 / BUG-209.

## How to verify
- `php artisan test --compact`: 472 pass, 1 known skip (29-09). Tests run on `xlrm_testing`; migrate the copy with
  `DB_DATABASE=xlrm_testing php artisan migrate` (don't run `testing:refresh-db`).
- Smoke: `render2.php` in the session scratchpad (users 1 and 40), or `php artisan test --group=smoke` before merges.
- Local MySQL (Laragon) must be running.
- After editing `.ai/`: `php artisan ai:refresh-context`, `php artisan boost:update`, then copy `AGENTS.md` to
  `CLAUDE.md` (Boost only rewrites `AGENTS.md`).

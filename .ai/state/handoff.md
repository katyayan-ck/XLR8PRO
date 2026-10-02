# Handoff — the one live state file (rewrite with every commit; `.ai/guidelines/10-workflow.md`)

**Updated:** 03-10-2026 · **Branch:** `dev/admin` · **Push pending:** the owner asked for a push on 03-10, but the agent's
`git push` was blocked by the tool's permission guard (and GitHub was unreachable once) — the owner runs
`git push origin dev/admin`. Local is 49+ commits ahead of `origin/dev/admin` (last pushed: `ad3824ee`, 01-10). Merge to
`stage` (N2) only on the owner's prompt.

## Where things are (DEC-086 layout)
- Guides: `tech-guides/README.md` (load map) · project card `tech-guides/00-project.md` · platform guides
  `tech-guides/platform/` (new: `17-help-support.md`).
- Records: `docs/todo.md` (Part 1 to-do, Part 2 accomplishments) · `docs/changelog.md` · `docs/bugs/open.md` /
  `closed.md` (index `.ai/state/bugs-index.md`) · date-wise `docs/daily/DD-MM-YYYY/` · `docs/decisions/decision-log.md`
  (last: **DEC-096**) · booking-team change log `docs/booking-team-changes.md` (BT-001 … BT-015) · owner answers
  `docs/owner-decisions-2026-10-01.md` · plans `tech-guides/frs-and-workflows/plans/`.
- Superseded files: `_backup/` (git-ignored; never read by agents). `booking.sql` stays untracked (booking team dump).

## Project state (summary; details in `docs/todo.md` Part 2 and `docs/daily/`)
- **On `stage`:** entity services, platform utilities, UI layer, data scoping (DEC-050…071).
- **On local `dev/admin` (push pending):** everything up to 03-10 — DEC-070…096, W1–W15, W16b–e, W18a–m (see below).
- **Local data (`xlrm`):** vehicle masters purged (DEC-051) awaiting a fresh import; no published price snapshots.
  KYC copies in history masked (W18h); one test support ticket each from the owner (#1 no screenshot, #2 washed-out
  screenshot — both from before the fixes).
- **Track B:** paused after B0 (resume from xceler8 `d9009db`).

## Just done (latest first; full detail in `docs/daily/02-10-2026/` and `docs/daily/03-10-2026/`)
- **03-10 — help & support (DEC-094 / DEC-096):**
  - W16b help engine + F1 pane + Help Centre; W16c page tours (Driver.js) + "new" dot; W16d diagnostics collector
    (`xl-diag.js`, server trail, masking).
  - W16e support requests, reworked to the owner's rules: diagnostics card on the ticket page for the support team only;
    no requester remark / remove; executive-only assignees; pane on / off switch `support.pane_requests`; user menu →
    My support tickets.
  - BUG-232 (screenshot never captured → html2canvas-pro) and BUG-233 (washed-out capture → copy without animations /
    overlays) fixed.
- **03-10 — infrastructure:** W18m Redis readiness (`.env` switch only) + BUG-230 queue `retry_after` 1900 s; Playwright
  E2E (`npm run e2e`) passes with the owner's local test account; BUG-231 logged (local threaded-Apache `.env` race).
- **02-10 / 03-10 — owner answers (DEC-095), W18:**
  - W18a/b app OTP login + API access.
  - W18c booking bugs BT-008 … BT-013.
  - W18d BT-014 `SLS_BKNG_ORDER_APPROVE`.
  - W18e coming-soon page (59 menu items, BT-015).
  - W18g `DB::` guard exemption.
  - W18h KYC history masking (run on the servers by the owner).
  - W18j `users:reset`.
  - W18k sign-in / password settings.
  - W18l generated `person_code`.
- Full suite on 03-10: see "How to verify".

## In progress / next
- **Next step: W16f** — finish `tech-guides/platform/17-help-support.md` (article-writing guide) and add the help-usage
  log (FRS §7: articles opened, searches with no result, tours finished, requests sent — a small log table + a report on
  the Help Centre for settings managers). Then the remaining unblocked work in `docs/todo.md` Part 1. The W17 manual /
  help-article content stays last (§13).
- **Blocked / waiting (do not start without the owner):**
  - W18f — the 5 booking reports (questions R1–R8 in `docs/owner-decisions-2026-10-01.md`, W18f section).
  - BUG-229 — what Order Verification's Reject should set.
  - W18j run + W18l remap — `users:reset` runs on the owner's account list (`--bin-dir=D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin`);
    afterwards remap the few remaining old `person_code`s (old → new map, owner's go).
  - W18i — variant codes / vehicle re-import with the pricing run (pricing decided later).
  - W15 rest (122 `DB::` uses / 7 files): deletions #6, D5, D23, D28, importers phase 5.
- **Working rules for booking code:** every change is a numbered BT entry (`docs/booking-team-changes.md`), checked with
  `APP_DEBUG=false DB_DATABASE=xlrm_testing php artisan dev:route-snapshot 1,40 <spec> before.json` before and
  `--compare=before.json` after, one commit each, revertable.

## Waiting on the owner
- Grant to designations: `UTL_SUPP_ADMIN` / `UTL_SUPP_EXEC` (support), `SLS_BKNG_ORDER_APPROVE` (order approval),
  `VEH_CONT_VIEW` / `VEH_CONT_EDIT` / `VEH_CMPR_VIEW` (vehicle content) — only superadmin holds them now.
- Answers: W18f R1–R8, BUG-229 Reject rule, `users:reset` account list, pricing (#22–27), D29, deletions #6, DEC-090,
  grants #32.
- Local machine: `php artisan config:cache` (BUG-231), or keep the occasional local 500.
- Firefox re-check of the support screenshot (Playwright here has Chromium only).
- BUG-228: SMS vendor / DLT details for the app OTP.

## How to verify
- `php artisan test --compact` (~5 min) on `xlrm_testing`: **613 passed, 1 known skip, 0 failures** on 03-10. Migrate the copy with
  `DB_DATABASE=xlrm_testing php artisan migrate` (never `testing:refresh-db`). Do not set `BASSET_CACHE_MAP=false` for
  the normal suite.
- E2E: `npm run e2e` with `E2E_USER` / `E2E_PASSWORD` (owner's local test account; Windows cmd:
  `set "E2E_USER=…" && set "E2E_PASSWORD=…" && npm run e2e`).
- Help & support: `php artisan test --compact tests/Feature/Platform/HelpTest.php tests/Feature/Platform/DiagnosticsTest.php tests/Feature/Platform/SupportRequestTest.php`.
- Local MySQL (Laragon) must be running. After editing `.ai/`: `php artisan ai:refresh-context`.

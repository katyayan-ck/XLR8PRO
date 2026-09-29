<!-- Date-wise copy of .ai/state/handoff.md for 30-09-2026: kept identical to it through the day (rewrite both with every
     commit); from the next day it stays as that day's closing hand-over. -->

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
1. **U11 API docs:** notifications / alerts / messages, documents, history, webhooks (`tech-guides/api/`).
2. **U7 web side:** admin flashes through the same codes / language file.
3. **U1 per screen:** header-less Backpack form cards get headers when converted; a real-browser check of quotation /
   booking forms.

## Waiting on the owner
- **History rewrite:** commit `4c82d28` accidentally added `docs/reference/XLRM-Pricing-data/` (~18 MB workbooks /
  PDFs). It is now untracked and in `_backup/`, but still in history. Not pushed — rewriting `dev/admin` history before
  the next push removes it (needs approval).
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

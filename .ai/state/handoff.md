# Handoff (rewrite with every commit — `.ai/guidelines/10-workflow.md`)

**Updated:** 29-09-2026 · **Branch:** `dev/admin` · **Pushed:** no (stage merge waits on the owner; origin/stage has 4
reverts by the booking team).

## Just done (latest first)
- U1 / U3 / U4: collapsible + draggable form cards with required badges (`xl-ui.js`), density settings `ui.density.*` + Appearance controls, lazy images; `UiDensityTest`.
- U11 API docs: `docs/api/index.md`, pricing + system-settings docs and Postman collections; BUG-207 logged (settings API exposure).
- U8 branded error pages (public + admin in-shell, 500 reference id in logs); the accomplishments log rule + `docs/accomplishments/29-09-2026.md`.
- BUG-198 fixed:
  - `Role` declares its table; `User::deniesPermission()` is memoised;
  - a permission-cache rebuild takes 9 queries / 312 ms (it was 2,887).
- Standing rules added for all agents:
  - UI density / collapsible cards / UI kit / density control / lazy loading / error pages;
  - one error pipeline;
  - guides on every change;
  - module-wise API docs + Postman;
  - changelog + status + handoff per commit;
  - commented + formatted code.
- DEC-084 security baseline:
  - idle logout + screen lock (Settings, off by default);
  - security headers + CSP report-only;
  - self-service switches + password rule.
- The formats inventory and draft data dictionary (`docs/reference/data-dictionary-draft.md`); BUG-206
  (`person_code` = PAN / Aadhaar).
- DEC-083 pricing masters, auto recalculation, sync stamp, logo; DEC-073…082 pricing redesign.

## In progress / next (to-do `docs/plans/2026-09-29-go-live-todo.md`)
1. **U11 API docs:** remaining modules: auth, devices, notifications / alerts / messages, documents, history, webhooks.
2. **U7:** the central error pipeline (inventory first, then `withExceptions`); fix the 422 vs 400 enum mismatch and
   the unregistered `E002` in `validate_device`.
3. **U1 per screen:** header-less Backpack form cards get headers when converted; a real-browser check on quotation /
   booking forms (local MySQL had to be started by hand on 29-09; the web server was not running).

## Waiting on the owner
- **Data dictionary:** 5 questions (activity abbreviations, the person-code remap, the keyword clean-up, designations,
  location / employee formats).
- **P0 decisions:** D1–D3, D13, D23, D29, N2 (stage merge).
- **Security policy values (N4):** idle minutes, lock, password expiry / history, email / mobile self-service, token
  expiry.
- **Package approvals:** 2FA, backups, error tracking, browser tests.

## How to verify
- `php artisan test --compact`: 458+ pass, 1 known skip.
- Smoke: `render2.php` in the session scratchpad, users 1 and 40.
- Tests run on `xlrm_testing` (never `testing:refresh-db`; migrate the copy with
  `DB_DATABASE=xlrm_testing php artisan migrate`).

# Changelog — 03-10-2026

Today's changes only (the date-wise copy). The same entries are in the cumulative `docs/changelog.md` under
`## 2026-10-03`; add every new entry to **both** (`.ai/guidelines/10-workflow.md`).

### W18m (Redis part) — Redis readiness; queue `retry_after` fixed (DEC-095 #33; BUG-230)
- **Files:** `config/queue.php` (`retry_after` 90 → 1900 s on the database and Redis connections), `.env.example` (Redis
  switch notes), `tech-guides/platform/16-reference.md` §4 (retry_after + Redis section), new
  `tests/Feature/Platform/QueueRetryAfterTest.php`; BUG-230 (found and fixed).
- **Review result:** no app code reads the cache / jobs / session tables. `Cache::lock` works on Redis. `Cache::flush()`
  (keyword cache, pricing reset) clears only the Redis cache database (`REDIS_CACHE_DB` 1), not sessions / queue (DB 0).
  So the switch is `.env` only, documented for IT (phpredis is already in the PHP build; no Composer package).
- **Bug found:** `retry_after` 90 s < pricing job timeout 1800 s, so with two workers a running import would be started
  again. The defaults are now 1900 s; the test fails if any job's `$timeout` reaches it.
- **Checked:** `QueueRetryAfterTest` (fails on the old values, passes now).
- **Not done:** Playwright E2E — installing `@playwright/test` (dev dependency) and its Chromium download needs the
  owner's go (new dependency / machine change). The plan is an E2E smoke (login → dashboard → bookings) under
  `tests/E2E`.

### Owner 03-10: Playwright and the UAT / production KYC masking approved (DEC-095 #34, #19)
- **Files:** new `playwright.config.ts` (dev-only, base URL / credentials from env) and `tests/E2E/smoke.spec.ts`
  (sign in → dashboard → bookings → coming-soon page, no error page); `.gitignore` ignores `/test-results`,
  `/playwright-report`.
- **Not done by the agent (blocked by the tool's permission guard, left to the owner):**
  1. `npm i -D @playwright/test` and `npx playwright install chromium`.
  2. Adding `php artisan privacy:mask-kyc-history --apply || echo "KYC masking failed"` to `deploy-hook.sh`. It must
     be non-fatal: the workflow stops on a failing step while the site is `down`. It runs idempotently on stage, UAT and
     production at their next deploy.
- **Owner later (unchanged):** booking report definitions R1–R8, the Reject rule (BUG-229), the account list for
  `users:reset`.

### Full-suite checkpoint fixes (03-10): person CRUD test, KYC restore robustness (W18l / W18h follow-up)
- **Full suite (02-10 → 03-10 run):** 598 passed, 1 skipped, 2 failed — both from this session's changes.
  - `tests/Feature/Admin/Org/PersonCrudTest.php`: the old "Aadhaar takes priority when deriving the person code" test
    asserted the behaviour W18l removed (BUG-206). It now asserts a generated `PERS-` code with the Aadhaar / PAN
    stored on the person.
  - `KycHistoryMaskingService::restore()` crashed (`DecryptException`) on the backups the real `--apply` left in
    `xlrm_testing`: tests run with phpunit's `APP_KEY`. It now returns `array{restored, failed}`; a backup it cannot
    decrypt is left in place and logged, and the rest are restored. `--restore` exits 1 when any failed. The test owns
    its backup rows and covers the skip.
- **Files:** `app/Services/Platform/Privacy/KycHistoryMaskingService.php`, `app/Console/Commands/MaskKycHistory.php`,
  `tests/Feature/Platform/KycHistoryMaskingTest.php`, `tests/Feature/Admin/Org/PersonCrudTest.php`, guide
  `16-reference.md` §8.
- **Checked:** both tests pass (9 tests); PHPStan clean.

### W16b — F1 help engine, pane and Help centre (DEC-094)
- **Files (new):**
  - `app/Services/Platform/Help/HelpService.php`.
  - `app/Http/Controllers/Admin/Utils/Platform/HelpController.php`.
  - Views `resources/views/admin/utils/platform/help/{index,show}.blade.php`.
  - `public/js/xl-help.js`.
  - `resources/help/` (articles folder; content comes last, §13).
  - `tests/Feature/Platform/HelpTest.php`.
  - Guide `tech-guides/platform/17-help-support.md` (+ README row).
- **Changed:**
  - `routes/backpack/utils.php`: `utils.help.{index,pane,search,show}`.
  - `config/platform.php`: `help.path`.
  - `resources/lang/en/utils.php`: `help.*`.
  - `public/css/xl-ui.css`: `.xl-help-*`.
  - `header_metas.blade.php`: `xl-help` meta + script on signed-in pages.
  - `topbar_right_content.blade.php`: `?` button.
- **Behaviour:**
  - F1 / Shift+? / `?` opens a right-side pane with the current screen's article (route name → article, else the
    process overview, else "not written yet", logged).
  - `::: can CODE` sections show only to holders. Articles can be limited to permissions. Raw HTML is escaped.
  - Search covers only what the user may see. The Help centre lists articles by module, plus screen coverage for
    settings managers.
  - Nothing loads until the first F1.
- **Checked:**
  - `HelpTest` (4 tests: sections per permission, overview / missing fallbacks, article permissions + search without
    hidden text, escaped HTML, pane on every page).
  - Platform / menu / UI / lang suites: 91 passed.
  - Dashboard, Help centre, pane JSON and bookings → 200 as superadmin and user 40. PHPStan clean.

### W16c — on-demand page tours and the "new" dot (DEC-094)
- **Files:**
  - `public/js/xl-help.js`: `fetchPane()` shared, `tourSteps()` (exposed as `XL.help.tourSteps`), `runTour()`,
    "Take the tour" button, `?tour=1` start, "new" dot via localStorage.
  - `header_metas.blade.php`: meta `article {key, updated, tour}`; Driver.js 1.3.1 CSS / JS through `@basset` only
    when the screen's article has a tour.
  - `HelpService::forRoute()` gains `$logMissing` (false for the per-page lookup).
  - `resources/lang/en/utils.php`: `help.new`, `tour_next / prev / done / empty`.
  - `public/css/xl-ui.css`: dot.
  - `tests/Feature/Platform/HelpTest.php` (+1 test, +1 assertion).
  - Guide `17-help-support.md` (page tours section); plan / to-do status.
- **Behaviour:**
  - The tour highlights each step's element. Steps whose element is missing or hidden (permission / state) are
    skipped.
  - No step left → a note in the pane, nothing runs.
  - The tour never changes data and is never forced.
- **Checked:**
  - `HelpTest` 5 passed: Driver.js only where a tour exists; meta article / tour.
  - Lang tests pass. JS syntax checked.
  - Screens unchanged except the meta (+300 bytes on every page, superadmin and user 40, all 200).

### Playwright E2E running (DEC-095 #34); KYC masking run by the owner (D26)
- **Owner 03-10:** installed `@playwright/test` 1.63.0 + Chromium (`package.json`, `package-lock.json`) and ran
  `privacy:mask-kyc-history` on the servers. Local re-check: the report finds 0 Aadhaar / PAN values on `xlrm` and
  `xlrm_testing`.
- **Fixes:**
  - `playwright.config.ts`: the base URL now always ends with `/`. Without it, `admin/login` resolved to
    `/xlrm/admin/login` (404) under the sub-folder app URL.
  - `tests/E2E/smoke.spec.ts`: a new sign-in-page check needs no login; the signed-in smoke skips until
    `E2E_USER` / `E2E_PASSWORD` are set.
  - `package.json`: `npm run e2e`.
- **Checked:** `npx playwright test` → 1 passed (sign-in page), 1 skipped (no test credentials).

### W16d — diagnostics collector for support requests (DEC-094)
- **Files (new):**
  - `public/js/xl-diag.js`.
  - `app/Services/Platform/Help/DiagnosticsService.php`.
  - `app/Http/Middleware/RecordRequestTrail.php`.
  - `tests/Feature/Platform/DiagnosticsTest.php`.
- **Changed:**
  - `config/backpack/base.php`: trail middleware added to the admin stack, after the auth guards and before the
    redirecting idle / password guards.
  - `header_metas.blade.php`: `xl-diag` meta, plus html2canvas 1.4.1 cached by Basset and loaded only when a screenshot
    is taken.
  - Guide `17-help-support.md`; plan / to-do status.
- **Behaviour:**
  - Per tab, the last 50 actions, network calls and JS errors (field names, never values).
  - Per user on the server, the last 50 requests for 2 hours (route, status, time, error ref).
  - Masking of Aadhaar / PAN / mobile / e-mail / tokens and secret URL parameters.
  - A screenshot with password / OTP / `data-xl-sensitive` fields blanked.
- **Checked:**
  - `DiagnosticsTest` (masking, URL cleaning, trail cap, heartbeat skipped, page wiring).
  - Platform / IAM / admin-auth / architecture suites: 116 passed.
  - Pages 200 for superadmin and user 40. PHPStan clean; JS syntax checked.

### E2E smoke passes end to end (DEC-095 #34)
- `npx playwright test` with an owner-provided local account (passed in `E2E_USER` / `E2E_PASSWORD` only): 2 passed —
  the sign-in page, then sign in → dashboard → bookings list → coming-soon page with no error page.
- Windows cmd usage: `set "E2E_USER=…" && set "E2E_PASSWORD=…" && npm run e2e` (quotes keep the trailing space out of
  the value); bash: `E2E_USER=… E2E_PASSWORD=… npm run e2e`.

### W16e — "Still need help?" support requests (DEC-094)
- **Files (new):**
  - Migration `2026_10_03_004646_create_support_requests_dec094.php`: permissions `UTL_SUPP_ADMIN` / `UTL_SUPP_EXEC`
    (UTL / SUPP, superadmin only), table `xlr8_utils_support_request`, `TICKET_CATEGORY` `SUP_*` ×5. Run on `xlrm` +
    `xlrm_testing`.
  - `app/Models/Utilities/Support/SupportRequest.php`.
  - `app/Services/Platform/Help/SupportRequestService.php`.
  - `app/Http/Controllers/Admin/Utils/Platform/SupportController.php`.
  - `app/Jobs/Platform/PurgeSupportBundles.php`.
  - View `admin/utils/platform/support/index.blade.php`.
  - `tests/Feature/Platform/SupportRequestTest.php`.
- **Changed:**
  - `routes/backpack/utils.php`: `utils.support.{index,store,download,assign}`.
  - `routes/console.php`: purge daily 02:45.
  - `config/platform.php`: `support.*` settings.
  - `resources/lang/en/utils.php`: `support.*`.
  - `header_metas.blade.php`: support endpoints / labels.
  - `public/js/xl-help.js`: pane form.
  - `public/js/xl-diag.js`: JPEG screenshot.
  - Help centre link.
  - Guides `17-help-support.md`, `16-reference.md`; `.ai/rules/admin-backpack.md` (UTL `SUPP`).
- **Behaviour:**
  - The pane's "Still need help?" form opens a `SUP_*` ticket (P2 when urgent). Its owner is the support admin with
    the fewest open support tickets, who is notified.
  - The masked diagnostic zip goes to private storage. Only the requester, support admins and assigned executives
    may download it (not the ticket desk).
  - Admins assign only support executives. The zip is deleted after 90 days (setting).
- **Checked:**
  - `SupportRequestTest` (3 tests: owner choice, zip files + masking, download rights, executive-only assignment,
    purge → 410, bad category → 422).
  - Platform / IAM / architecture / lang / menu suites: 123 passed.
  - Pages 200 for superadmin and user 40. PHPStan clean; JS syntax checked.

### BUG-231 logged — intermittent local 500s from the threaded Apache `.env` race
- Found by the E2E smoke (1 of 3 runs): a request ran without `.env` (SQLite session store / `production` without
  APP_KEY). Not an app bug; local fix = `php artisan config:cache` (owner's call — local environment change). Entry in
  `docs/bugs/open.md`.

### Support requests: menu entries and an on-screen diagnostics viewer (owner question 03-10)
- **Owner asked:** where the Support Requests menu is and how to open the attached data.
  - Before: reachable only through `?` → Help Centre → button.
  - The zip had to be downloaded and unpacked.
- **Changed:**
  - `menu_items.blade.php`: Utilities → **Help Centre** and **Support Requests** (every signed-in user).
  - New `utils.support.show` + view `support/show.blade.php` (screenshot, page facts, tabs for actions / network
    calls / script errors / server requests + log lines / user & access). Same rights as the download (requester,
    support admins, assignees), 410 once purged.
  - `SupportRequestService::bundleContents()`.
  - List: **Diagnostics** opens the viewer; a zip icon still downloads.
  - Lang `support.download_zip`, `support.diagnostics_title`; guide `17-help-support.md`.
- **Checked:**
  - New test (opens for the requester with the screenshot and masked values, 403 for an outsider).
  - `SupportRequestTest` + `MenuLinksTest` 6 passed; pages 200 for superadmin and user 40.

### Support diagnostics move onto the ticket page, support team only (owner 03-10, DEC-094)
- **Owner asked:**
  - No separate section: the diagnostics belong on the ticket page, for the assigned users / snoopers.
  - Not for the user who created the ticket.
  - Remove the "Remove" button the requester saw.
- **Cause of that button:** the request posted a chat remark in the requester's own name ("Diagnostics attached…"),
  which its author may delete — and which told the requester what was shared.
- **Changed:**
  - `SupportRequestService`: no remark is posted. `canDownload()` / `assign()` replaced by `canViewDiagnostics()`
    (support admins + the ticket's owner / assignees / snoopers, never the requester) and `forTicket()`.
  - `TicketService`: `isSupportDesk()` (support admins see / manage every `SUP_*` ticket), `supportExecutiveIds()`;
    `update()` refuses non-executive assignees on support tickets (`SUPPORT_NOT_EXECUTIVE`).
  - `TicketController::show` passes the request + decoded zip only to the support team, and executive-only assignee
    options for support tickets.
  - `tickets/show.blade.php` includes the new **Diagnostics** card (`support/_diagnostics.blade.php`, formerly the
    viewer page).
  - `SupportController` keeps only `store` / `download` (support team only).
- **Removed:**
  - Routes `utils.support.index`, `.show`, `.assign`; the view `support/index.blade.php`.
  - The Utilities → Support Requests menu item and the Help Centre button.
  - Unused lang keys. The pane's "sent" message now points to Utilities → Tickets.
- **Data:** the one remark of the owner's local test request (thread 1284) was removed permanently (local `xlrm` only).
- **Checked:**
  - `SupportRequestTest` 4 passed: the requester gets 403 on the zip and no card / remark / Remove on the ticket. Admin,
    assignee and snooper see the card and download. A non-executive assignee is refused. Purge → 410.
  - Platform / menu / lang / architecture suites: 94 passed. Pages 200 for superadmin and user 40. PHPStan clean.

### BUG-232 — support screenshot never captured; silent failure fixed
- Owner 03-10: ticket #1 (Shankar Giri, My Account) has no screenshot.
- **Cause, reproduced in Chromium:** html2canvas 1.4.1 throws on Tabler 1.4's modern CSS colour functions
  (`Attempting to parse an unsupported color function "color"`) on every page. The pane caught the error and sent the
  request without a screenshot, without telling anyone.
- **Changed now:** `public/js/xl-help.js` shows "A screenshot of this page could not be taken…" in the form and logs the
  reason through `console.error`, so it lands in the diagnostics' `errors.json`. New lang key
  `support.screenshot_failed`.
- **Pending owner approval:** swap to `html2canvas-pro` (MIT drop-in fork). It was verified working on the same page in
  a probe; nothing was installed.

### BUG-232 fixed — screenshots captured again with html2canvas-pro (owner approved 03-10)
- `resources/views/vendor/backpack/ui/inc/header_metas.blade.php`: html2canvas 1.4.1 → **html2canvas-pro 1.5.11** (MIT,
  drop-in fork with the same `window.html2canvas` API; Basset-cached, loaded only when a screenshot is taken). Guide
  `17-help-support.md` updated; BUG-232 moved to closed.
- **Checked** (Chromium, the app's own loading path, owner's local test account): `XL.diag.screenshot()` returns a JPEG
  on My Account (82 KB), dashboard (66 KB), bookings (131 KB) and tickets (47 KB). Before, every page failed with
  `Attempting to parse an unsupported color function "color"`. One run hit a blank page from BUG-231 (local env race),
  not the screenshot.
- Ticket #1 (Shankar Giri) was sent before the fix, so it has no screenshot. New requests will include one.

### Support: on / off switch for pane requests; "My support tickets" in the user menu (owner 03-10, DEC-094)
- **Owner asked:**
  - A site-settings switch for the "Still need help?" auto-ticket.
  - A My Account menu option where every signed-in user sees all the tickets they created and can create a new one.
- **Files:**
  - `config/platform.php`: `support.pane_requests` (bool, default on).
  - `header_metas.blade.php`: `support: null` when off, so the pane shows no button.
  - `SupportController`: `store` answers 403 `SUPPORT_PANE_DISABLED` when off; new `mine()` / `open()`.
  - Routes `utils.support.mine`, `utils.support.open`.
  - New view `support/mine.blade.php`: own tickets list plus a New support ticket form.
  - `menu_user_dropdown.blade.php`: **My support tickets** under My Account.
  - Lang `support.*` (new keys; the "sent" message points there).
  - Guides `17-help-support.md`, `16-reference.md`.
- **Behaviour:**
  - A plain ticket has no diagnostics and is routed like a pane request: `SUP_*` category, P2 when urgent, owned by the
    least-loaded support admin.
  - The list shows only the user's own tickets. "My support tickets" works whatever the switch says.
- **Checked:**
  - `SupportRequestTest` 6 passed: switch off → 403 + no pane button; the menu link; the plain ticket's owner /
    priority / no zip; the list shows own tickets only; the requester opens their ticket.
  - Platform / IAM / menu / lang / architecture suites: 126 passed.
  - Pages 200 for superadmin and user 40. PHPStan clean.

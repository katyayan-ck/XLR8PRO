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

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

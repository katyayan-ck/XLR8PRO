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

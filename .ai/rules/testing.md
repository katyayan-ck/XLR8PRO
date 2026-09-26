---
description: Test database, test style and verification harnesses. Load when writing or running tests.
paths:
  - tests/**
  - phpunit.xml
---

# Testing

- PHPUnit 11 (Track A). Tests run on **`xlrm_testing`** — a full local copy of `xlrm`
  (`php artisan testing:refresh-db --force`, uses `MYSQL_BIN_DIR`). Never point tests at `xlrm`.
- Use `DatabaseTransactions` (tests rely on real reference data: RTO rules, org users, settings).
  **Never `RefreshDatabase`** — it would wipe the copy's reference data.
- Known pre-existing failures: see `.ai/state/current.md` — a change must not add new ones.
- Test services directly (`app(SomeService::class)`); set the actor with
  `$this->app['auth']->guard('backpack')->setUser($user)`.
- Regression tests replace "documents the crash" tests once a bug is fixed.
- If data a test needs may be absent, `markTestSkipped()` with the reason rather than fabricating half a domain.
- HTTP verification outside PHPUnit: boot the app against `xlrm_testing`, log in via
  `auth(backpack_guard_name())->loginUsingId($id)`, `$kernel->handle(Request::create(...))`, one request per
  process (re-bootstrapping in one process breaks the Blade component registry). API: create a
  `DeviceSession` + `createToken('x', ['device_id:<id>'])` inside a rolled-back transaction.
- Smoke-test the touched screens as superadmin **and** a scoped user (e.g. user 40) — superadmin hides permission bugs.
- Full-screen sweep = `tests/Feature/Admin/AdminScreenSmokeTest.php` (group `smoke`, excluded from the default suite; ~350 requests, minutes). Run before merges; add newly broken-but-tracked screens to its `KNOWN_BROKEN` list with the bug id.

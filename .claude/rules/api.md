---
description: REST API v1 used by the mobile app. Load for API controllers, routes or middleware.
paths:
  - app/Http/Controllers/Api/**
  - routes/api.php
  - app/Http/Middleware/**
---

# API v1 (mobile app contract — keep backward compatible, DEC-004)

- Prefix `/api/v1`. Public: `auth/request-otp`, `auth/verify-otp`. Everything else: `auth:sanctum` +
  `validate_device` (token must carry a `device_id:<id>` ability matching a live `xlr8_iam_device_session`).
- Admin-only groups use permission middleware (e.g. `permission:UTL_SETTINGS_MANAGE`); the `role`,
  `permission`, `role_or_permission` aliases are registered in `bootstrap/app.php`.
- Controllers extend `App\Http\Controllers\BaseController`; respond with `successResponse()` /
  `handleException()` → envelope `{http_status, success, code, message, timestamp, data}`.
- Never call `$this->middleware()` in controllers (doesn't exist on Laravel 11+; BUG-159) — use route middleware.
- Fetch models with `findOrFail()` in the method body (no implicit binding); read input via
  `$request->input()` / validated arrays.
- Every route must map to an existing method; verify with a device-bound token round trip
  (see `.ai/rules/testing.md`) — `route:list` alone doesn't prove it works.
- Changing a response shape = breaking the mobile app: add fields, don't rename/remove; new shapes go to v2 (Track B).
- Never log OTPs, tokens or full phone numbers.

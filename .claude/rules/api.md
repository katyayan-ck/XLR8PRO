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
- Errors: every API error is the envelope with a registered `ErrorCodeEnum` code and its HTTP status; unhandled
  exceptions go through the central handler (never a raw HTML page or stack trace to the app).
- **API documentation (user standing instruction, 29-09-2026)** — module-wise, kept in step with every API change:
  - `tech-guides/api/{module}.md`, one section per endpoint: method + URI + route name, auth / middleware, path / query /
    body params with their validation rules, every possible response (success and each error code) as JSON examples
    with field descriptions, and notes (rate limits, versioning, DEC ids);
  - `tech-guides/api/postman/{module}.postman_collection.json` — a Postman v2.1 collection with one request per endpoint
    (`{{base_url}}`, `{{token}}` variables, example bodies, saved example responses);
  - the OpenAPI annotations on the controller match both;
  - `tech-guides/api/index.md` lists the modules.

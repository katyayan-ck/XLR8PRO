# API v1: module index

This is the mobile app contract (DEC-004: v1 stays backward compatible; new shapes go to v2).

**Rule:** `.ai/rules/api.md`, API documentation. Each module has:
- `docs/api/{module}.md`: every endpoint with auth, parameters + validation, every response (success and each error)
  as JSON with field notes;
- `docs/api/postman/{module}.postman_collection.json`: Postman v2.1, variables `{{base_url}}`, `{{token}}`.

**Common to all:**
- Base `{{base_url}}/api/v1`; JSON envelope `{http_status, success, code, message, timestamp, data | errors}`.
- Everything except `auth/request-otp` and `auth/verify-otp` needs `Authorization: Bearer {token}` (Sanctum) plus a live
  device session (`validate_device`).
- OpenAPI UI: `GET /api/v1/documentation` (l5-swagger, generated from the controller annotations).

| Module | Endpoints | Doc | Postman | Status |
|---|---|---|---|---|
| Pricing (PRC) | `GET vehicle/pricing/{oemCode}` | [pricing.md](pricing.md) | [pricing](postman/pricing.postman_collection.json) | ✅ 29-09 |
| Auth | `POST auth/request-otp`, `POST auth/verify-otp`, `GET auth/me`, `POST auth/logout` | [auth.md](auth.md) | [auth](postman/auth.postman_collection.json) | ✅ 29-09 — BUG-187 (mobile login broken) and BUG-188 open |
| Devices (push tokens) | `GET devices`, `POST devices/register`, `POST devices/revoke-all`, `DELETE devices/{device_id}` | [devices.md](devices.md) | [devices](postman/devices.postman_collection.json) | ✅ 29-09 — BUG-210 fixed |
| System settings | `GET system-settings`, `GET system-settings/{key}`, `PUT system-settings/{key}`, `GET system-settings/topic/{topic}`, `GET system-settings/category/{dealership,pricing,site}`, `GET system-settings/export/json`, `POST system-settings/import/json` | [system-settings.md](system-settings.md) | [system-settings](postman/system-settings.postman_collection.json) | ✅ 29-09 — BUG-207 (exposure) open |
| Notifications / alerts / messages | `GET notifications`, `GET notifications/unread`, `POST notifications/{id}/read`, `POST notifications/mark-all-read`, `DELETE notifications/{id}`, `GET alerts`, `POST alerts/{id}/read`, `GET/POST messages/user/{user_id}`, `POST messages/{id}/read` | — | — | 🔴 |
| Documents | `POST docs/upload`, `GET docs/my`, `GET docs/search`, `GET docs/analytics`, `POST docs/groups`, `POST docs/groups/{groupId}/add`, `DELETE docs/groups/{groupId}/remove/{docId}`, `GET docs/groups/{groupId}/zip`, `POST docs/{docId}/approve` | — | — | 🔴 — BUG-182 (entity access) |
| History (chat) | `GET history/{entityType}/{entityId}`, `POST history/{entityType}/{entityId}/thread` | — | — | 🔴 — BUG-182 |
| Webhooks (comms) | `POST /api/webhooks/comms/{channel}` (HMAC-signed, not Sanctum) | — | — | 🔴 |
| Booking / Enquiry / Lead | No v1 routes today | booking.md / enquiry.md / lead.md (empty placeholders) | — | — (the files are kept for the future endpoints) |

## Errors common to every endpoint (DEC-085)
Every error on `api/*` has the envelope, whether a controller answered it or it was uncaught
(`App\Exceptions\ApiExceptionRenderer`):

```json
{ "http_status": 404, "success": false, "code": "RESOURCE_NOT_FOUND", "message": "Requested resource not found.",
  "timestamp": "2026-09-29T21:40:00+05:30" }
```

| HTTP | Code | When | Extra fields |
|---|---|---|---|
| 401 | `AUTH_UNAUTHORIZED` (message `Unauthenticated.`) | No / invalid / expired Sanctum token | — |
| 401 | `E002` | Token without a live device session (`validate_device`; the code is renamed only with the app team) | — |
| 403 | `AUTH_FORBIDDEN` / a module code | Missing permission | — |
| 404 | `RESOURCE_NOT_FOUND` / a module code (e.g. `PRICING_NOT_FOUND`) | Unknown route or record | — |
| 405 | `REQUEST_METHOD_NOT_ALLOWED` | Wrong HTTP method | `Allow` header |
| 422 | `VALIDATION_FAILED` / a module code | Invalid input | `errors: {field: [messages]}` |
| 423 | `RESOURCE_LOCKED` / a module code (e.g. `PRICING_ON_HOLD`) | Locked / on hold | — |
| 429 | `REQUEST_RATE_LIMITED`, `AUTH_OTP_RATE_LIMIT` | Throttled | `Retry-After` header |
| 500 | `SYSTEM_ERROR` | Unexpected; nothing internal is shown | `error_ref`: quote it to support (it is in the log entry) |
| 503 | `SERVICE_UNAVAILABLE` | Maintenance | — |

**Codes and messages:** `App\Enums\ErrorCodeEnum` (code → HTTP status) and `resources/lang/en/errors.php` (code →
message, grouped by module). Apps should branch on `code`, not on `message`.

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
| Auth | `POST auth/request-otp`, `POST auth/verify-otp`, `GET auth/me`, `POST auth/logout` | auth.md (empty) | — | 🔴 — note BUG-187 (mobile login broken) and BUG-188 |
| Devices | `GET devices`, `POST devices/register`, `POST devices/revoke-all`, `DELETE devices/{id}` | — | — | 🔴 |
| System settings | `GET system-settings`, `GET system-settings/{key}`, `PUT system-settings/{key}`, `GET system-settings/topic/{topic}`, `GET system-settings/category/{dealership,pricing,site}`, `GET system-settings/export/json`, `POST system-settings/import/json` | [system-settings.md](system-settings.md) | [system-settings](postman/system-settings.postman_collection.json) | ✅ 29-09 — BUG-207 (exposure) open |
| Notifications / alerts / messages | `GET notifications`, `GET notifications/unread`, `POST notifications/{id}/read`, `POST notifications/mark-all-read`, `DELETE notifications/{id}`, `GET alerts`, `POST alerts/{id}/read`, `GET/POST messages/user/{user_id}`, `POST messages/{id}/read` | — | — | 🔴 |
| Documents | `POST docs/upload`, `GET docs/my`, `GET docs/search`, `GET docs/analytics`, `POST docs/groups`, `POST docs/groups/{groupId}/add`, `DELETE docs/groups/{groupId}/remove/{docId}`, `GET docs/groups/{groupId}/zip`, `POST docs/{docId}/approve` | — | — | 🔴 — BUG-182 (entity access) |
| History (chat) | `GET history/{entityType}/{entityId}`, `POST history/{entityType}/{entityId}/thread` | — | — | 🔴 — BUG-182 |
| Webhooks (comms) | `POST /api/webhooks/comms/{channel}` (HMAC-signed, not Sanctum) | — | — | 🔴 |
| Booking / Enquiry / Lead | No v1 routes today | booking.md / enquiry.md / lead.md (empty placeholders) | — | — (the files are kept for the future endpoints) |

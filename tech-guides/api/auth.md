# API — Auth: OTP login, profile, logout (module IAM)

**Base URL:** `{{base_url}}/api/v1/auth`.
**Postman collection:** [`postman/auth.postman_collection.json`](postman/auth.postman_collection.json).
**Source:** `app/Http/Controllers/Api/V1/AuthController.php` → `App\Services\AuthService`.
**Envelope and common errors:** [index.md](index.md#errors-common-to-every-endpoint-dec-085).

> **Fixed 02-10-2026 (DEC-095):** the user is found by the person's **primary mobile** (`users` has no mobile column,
> BUG-187); `name`, `email` and `mobile` in the responses come from the person (`display_name`, `primary_email`,
> `primary_mobile`); the OTP is generated with `random_int()` (BUG-188); a token's expiry no longer resets when the row
> is updated (BUG-227). An unregistered mobile answers **404 `AUTH_USER_NOT_FOUND`**.
> ⚠ **BUG-228 (open):** the OTP **SMS** is still a placeholder (only logged) — the code reaches the user by e-mail only.

## Flow
1. `POST /request-otp` with the mobile → an OTP is sent (SMS + email). In `APP_ENV=local` the OTP is also returned.
2. `POST /verify-otp` with the mobile, OTP and device → a **Sanctum token** bound to that device (ability
   `device_id:{id}`).
3. Every other call sends `Authorization: Bearer {token}`; the `validate_device` middleware checks that the device session
   is still live.
4. `POST /logout` revokes the token.

**Limits** (constants in `AuthService`):

| Limit | Value |
|---|---|
| OTP length / validity | 6 digits / 10 minutes |
| OTP requests per mobile | 5 per 15 minutes → 429 |
| Wrong OTPs per mobile | 5 per 15 minutes → account locked 30 minutes (403) |
| Devices per user | 5 active in the last 30 days → 403 |

---

## POST /request-otp — send a login OTP
**Route:** `api.auth.request-otp`. **Auth:** none.

**Body (JSON):**

| Field | Validation | Notes |
|---|---|---|
| `mobile` | required, string, exactly 10 characters | 10-digit Indian mobile, no `+91` |

**200:**
```json
{ "http_status": 200, "success": true, "code": "S200", "message": "OTP sent to your registered mobile and email",
  "timestamp": "2026-09-29T21:40:00+05:30",
  "data": { "mobile": "9876543210", "expires_at": "2026-09-29T21:50:00+05:30", "expires_in_minutes": 10,
            "otp": "123456" } }
```
`otp` is present only when `APP_ENV=local`.

**Errors:**

| HTTP | Code | Message | When |
|---|---|---|---|
| 422 | `VALIDATION_FAILED` | `Validation failed` + `errors.mobile` | Missing / not 10 characters |
| 422 | `AUTH_MOBILE_INVALID` | `Invalid mobile number format. Must be 10-digit number.` + `errors.mobile` | Not 10 digits |
| 404 | `AUTH_USER_NOT_FOUND` | `Mobile number not registered in system` | Unknown mobile |
| 403 | `AUTH_USER_INACTIVE` | `User account is inactive. Contact support.` | Inactive user |
| 403 | `AUTH_FORBIDDEN` | `Account locked due to multiple failed attempts` | Locked (30 minutes) |
| 429 | `AUTH_OTP_RATE_LIMIT` | `Too many OTP requests. Maximum 5 allowed per 15 minutes.` | Rate limit |
| 500 | `SYSTEM_ERROR` + `error_ref` | `An unexpected error occurred` | Today, every request (BUG-187) |

```json
{ "http_status": 422, "success": false, "code": "VALIDATION_FAILED", "message": "Validation failed",
  "timestamp": "…", "errors": { "mobile": ["The mobile field is required."] } }
```

## POST /verify-otp — verify the OTP and get a token
**Route:** `api.auth.verify-otp`. **Auth:** none.

**Body (JSON):**

| Field | Validation | Notes |
|---|---|---|
| `mobile` | required, string, 10 characters | The same mobile |
| `otp` | required, string, 6 characters | The latest OTP for the mobile |
| `device_id` | required, string, max 255 | Stable per installation; the token is bound to it |
| `device_name` | required, string, max 255 | e.g. `Pixel 8` |
| `platform` | required, one of `android`, `Android`, `ios`, `iOS`, `web`, `Web` | |
| `platform_version` | string, max 50 (optional) | |
| `fcm_token` | string, max 255 (optional) | Push notifications |

**200:**
```json
{ "http_status": 200, "success": true, "code": "S200", "message": "OTP verified", "timestamp": "…",
  "data": { "token": "12|Qm9ndXM…", "expires_at": "2026-10-29T21:41:00+05:30",
            "user": { "id": 40, "name": "Asha Verma", "email": "asha@example.com", "mobile": "9876543210",
                      "role": "Sales Consultant" } } }
```
`role` is the user's first designation (`user` when none). The token is valid for **30 days** (`expires_at`). Store
it and send it as `Authorization: Bearer {token}`.

**Errors:**

| HTTP | Code | Message | When |
|---|---|---|---|
| 422 | `VALIDATION_FAILED` | `Validation failed` + `errors` | Invalid body |
| 401 | `AUTH_OTP_INVALID` | `Invalid OTP` | No OTP for the mobile, or a wrong OTP |
| 401 | `AUTH_OTP_EXPIRED` | `OTP has expired. Request a new one.` | Older than 10 minutes |
| 403 | `AUTH_FORBIDDEN` | `Too many failed OTP attempts` | 5 wrong OTPs → locked 30 minutes |
| 403 | `AUTH_DEVICE_BINDING_FAILED` | `Device limit exceeded. Maximum 5 devices allowed.` | Device limit |
| 404 | `AUTH_USER_NOT_FOUND` | `User not found` | The OTP's user was deleted |

## GET /me — the signed-in user
**Route:** `api.auth.me`. **Auth:** Bearer token + a live device session.

**200:**
```json
{ "http_status": 200, "success": true, "code": "S200", "message": "User profile retrieved", "timestamp": "…",
  "data": { "id": 40, "name": "Asha Verma", "email": "asha@example.com", "mobile": "9876543210",
            "role": "Sales Consultant", "permissions": ["SLS_ENQ_VIEW", "SLS_QUOT_CREATE"] } }
```
`permissions` = every permission the user has (through designations and direct grants).

**Errors:** 401 `AUTH_UNAUTHORIZED` (`Unauthenticated.`) without a token; 401 `E002` without a live device session.

## POST /logout — revoke the current token
**Route:** `api.auth.logout`. **Auth:** Bearer token + a live device session. **Body:** none.

**200:**
```json
{ "http_status": 200, "success": true, "code": "S200", "message": "Logged out successfully", "timestamp": "…" }
```
There is no `data` key.
Only the current token is revoked; the device session stays (see the Devices module to revoke devices).

**Errors:** as `GET /me`.

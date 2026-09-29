# API — Devices: push-notification registration (module IAM / UTL)

**Base URL:** `{{base_url}}/api/v1/devices`.
**Postman collection:** [`postman/devices.postman_collection.json`](postman/devices.postman_collection.json).
**Source:** `app/Http/Controllers/Api/V1/NotificationController.php` (`registerDevice`, `getUserDevices`,
`unregisterDevice`, `revokeAllDevices`) → `App\Services\FirebaseService`; rows in `xlr8_iam_user_device_token`
(`App\Models\IAM\UserDeviceToken`).
**Auth:** Bearer token + a live device session ([auth.md](auth.md)). **Envelope and common errors:**
[index.md](index.md#errors-common-to-every-endpoint-dec-085).

> These endpoints manage the **push token** of each of the user's devices (Firebase Cloud Messaging). They do **not**
> sign a device out: the sign-in lives in the device session + Sanctum token (`POST auth/logout` revokes the current
> token).

## POST /register — register (or refresh) this device's push token
**Body (JSON):**

| Field | Validation | Notes |
|---|---|---|
| `device_id` | required, string, max 255 | The same id used at `auth/verify-otp` |
| `device_name` | required, string, max 255 | |
| `platform` | required, one of `android`, `Android`, `ios`, `iOS`, `web`, `Web` | Stored lower-case |
| `platform_version` | string, max 50 (optional) | |
| `fcm_token` | required, string, max 255 | The FCM registration token |
| `metadata` | object (optional) | Merged into the stored metadata |

Registering the same `device_id` again **updates** the row (new FCM token, re-activated) — call it whenever FCM rotates
the token. (Before 29-09 every call failed with a 500: BUG-210.)

**201:**
```json
{ "http_status": 201, "success": true, "code": "S201", "message": "Device registered", "timestamp": "…",
  "data": { "id": 17, "user_id": 40, "device_id": "a1b2c3", "device_name": "Pixel 8", "platform": "android",
            "fcm_token": "eYk…", "is_active": true, "metadata": {}, "created_at": "…", "updated_at": "…" } }
```

**422:** `VALIDATION_FAILED` with `errors` (e.g. `errors.fcm_token`).

## GET / — the user's registered devices
**200:** `data` = a list of device rows (as above), most recently used first.

```json
{ "http_status": 200, "success": true, "code": "S200", "message": "User devices retrieved", "timestamp": "…",
  "data": [ { "id": 17, "device_id": "a1b2c3", "device_name": "Pixel 8", "platform": "android", "is_active": true,
              "last_used_at": "…" } ] }
```

## DELETE /{device_id} — remove one device's push token
**Path:** `device_id` — the device's `device_id` string (not the numeric row id). Only the caller's own devices.

- **200:** `{ …, "message": "Device unregistered" }` (no `data`).
- **404:** `{ "http_status": 404, "success": false, "code": "RESOURCE_NOT_FOUND", "message": "Device not found with ID a1b2c3", … }`

## POST /revoke-all — stop push to every device
Marks all the user's push tokens inactive (no more notifications to any device). It does not sign devices out.

**200:**
```json
{ "http_status": 200, "success": true, "code": "S200", "message": "All devices revoked", "timestamp": "…",
  "data": { "revoked": 3 } }
```

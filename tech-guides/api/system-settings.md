# API — System settings (module UTL)

**Base URL:** `{{base_url}}/api/v1/system-settings`.
**Postman collection:** [`postman/system-settings.postman_collection.json`](postman/system-settings.postman_collection.json).
**Source:** `app/Http/Controllers/Api/V1/SystemSettingApiController.php` → `App\Services\SystemSettingService` (the
legacy adapter over `xlr8_utils_system_setting`; the admin screen and new code use
`Platform\Settings\SettingsService`).
**Envelope and authentication:** as in [index.md](index.md) — a Sanctum token + a live device session.

> **Settings managers only (DEC-095, BUG-207 / BUG-209 fixed 02-10-2026):** every endpoint below needs
> `UTL_SETTINGS_MANAGE` (others get **403**). The app reads its settings from [`GET /app-settings`](app-settings.md).
> Encrypted settings are never listed or returned. `PUT {key}` and `import/json` write through `SettingsService` (typed,
> encrypted, audited). **App team:** stop calling `/system-settings` from the app; use `/app-settings`.

## Keys the app needs

| Key | Type | Use |
|---|---|---|
| `pricing.last_updated_at` | string (ISO-8601) | **Offline-sync stamp (DEC-083).** When it differs from the stored value, re-download vehicles / prices / accessories. It moves on any published-price, vehicle-master or accessory change. |
| `display.date_format`, `display.time_format` | string | Date display |
| `branding.logo` | image URL | Company logo (DEC-083) |

---

## GET / — all visible settings, grouped by topic
**Route:** `api.settings.index`

**200:**
```json
{ "http_status": 200, "success": true, "code": "S200", "message": "Settings retrieved successfully", "timestamp": "…",
  "data": { "pricing": { "pricing.last_updated_at": "2026-09-29T14:05:11+05:30", "pricing.rto.round_up_to": 1000 },
            "display": { "display.date_format": "d-M-Y" } } }
```

The topic is the `topic` column, else the key prefix before the first dot.

## GET /topic/{topic} — the settings of one topic
**Route:** `api.settings.topic`. **Path:** `topic` (string, e.g. `pricing`).

- **200:** `data` = `{ "key": value, … }` (typed: bool / int / float / array / string).
- **404:**

```json
{ "http_status": 404, "success": false, "code": "…NOT_FOUND", "message": "Topic 'xyz' not found", "timestamp": "…" }
```

## GET /category/site · /category/dealership · /category/pricing — shortcuts
**Routes:** `api.settings.site`, `api.settings.dealership`, `api.settings.pricing`.

**200** (`category/pricing`, used for the sync check):
```json
{ "http_status": 200, "success": true, "code": "S200", "message": "Settings for 'pricing' retrieved", "timestamp": "…",
  "data": { "pricing.dealer_charges.include_cod": false, "pricing.insurance.gst_pct": 18, "pricing.last_updated_at": "2026-09-29T14:05:11+05:30" } }
```

## GET /{key} — one setting (the raw row, see BUG-207)
**Route:** `api.settings.show`. **Path:** `key`, the dotted key (e.g. `pricing.last_updated_at`); `export/json` is
excluded.

**200:**
```json
{ "http_status": 200, "success": true, "code": "S200", "message": "Setting retrieved successfully", "timestamp": "…",
  "data": { "id": 57, "key": "pricing.last_updated_at", "label": "Pricing last updated — …", "value": "2026-09-29T14:05:11+05:30",
            "type": "string", "is_visible": true, "iseditable": true, "updated_at": "2026-09-29T08:35:11.000000Z" } }
```

**404:** `Setting 'x.y' not found`.

## PUT /{key} — update a setting (admin)
**Route:** `api.settings.update`. **Middleware:** `permission:UTL_SETTINGS_MANAGE`.

**Body (JSON):**

| Field | Validation | Notes |
|---|---|---|
| `value` | required | Typed by the setting |

> ⚠ **BUG-209:** `PUT /{key}` and `POST /import/json` currently always answer **403** (the authorization helper they
> use never passes, and there is no settings policy). They wait on the BUG-207 decision.

**Responses:**
- **200:** `data` = the updated row.
- **403:** without the permission.
- **404:** unknown key.
- **422:** `VALIDATION_FAILED` with `errors.value`.

## GET /export/json — export all settings (admin)
**Route:** `api.settings.export.json`. **Middleware:** `permission:UTL_SETTINGS_MANAGE`.

- **200:** `data` = a list of `{key, value, type, topic, …}` rows.

## POST /import/json — import settings (admin)
**Route:** `api.settings.import.json`. **Middleware:** `permission:UTL_SETTINGS_MANAGE` + the policy
`import SystemSetting`.

**Body:** a JSON array `[{ "key": "…", "value": …, "topic": "…" }, …]`.

**200:**
```json
{ "http_status": 200, "success": true, "code": "S200", "message": "Import completed", "timestamp": "…",
  "data": { "imported": 12, "failed": 1, "errors": [ { "key": "x.y", "error": "…" } ] } }
```

## Errors common to all
| HTTP | Code | When |
|---|---|---|
| 401 | `AUTH_UNAUTHORIZED` (no / invalid token) · `E002` (no live device session) | See [index.md](index.md#errors-common-to-every-endpoint-dec-085) |
| 403 | `…FORBIDDEN` | Admin endpoints without `UTL_SETTINGS_MANAGE` |
| 404 | `…NOT_FOUND` | Unknown topic / key |
| 422 | `VALIDATION_FAILED` | Invalid body |
| 500 | `SYSTEM_ERROR` + `error_ref` | Unexpected (logged with the same reference) |

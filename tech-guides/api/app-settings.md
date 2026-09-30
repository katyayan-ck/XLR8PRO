# API — App settings (module UTL, DEC-091)

**Base URL:** `{{base_url}}/api/v1/app-settings`.
**Postman collection:** [`postman/app-settings.postman_collection.json`](postman/app-settings.postman_collection.json).
**Source:** `app/Http/Controllers/Api/V1/AppSettingsController.php` → `SettingsCatalogue::appSettings()`; values come
from Utilities → Settings (`SettingsService`, cached, busted on every change).
**Auth:** Bearer token + a live device session ([auth.md](auth.md)). **Envelope and common errors:**
[index.md](index.md#errors-common-to-every-endpoint-dec-085).

> Everything the app needs from Settings in one call — branding, channel switches, which profile fields the user may
> change, display formats and the pricing sync stamp. No secrets. A change on the Settings screen shows on the next
> call; call it at start-up and when the app returns to the foreground. Prefer it over the broad `system-settings`
> endpoints (BUG-207).

## GET / — the app settings
**Route name:** `api.app-settings` · **Middleware:** `auth:sanctum`, `validate_device`. No parameters.

**200:**

```json
{
  "http_status": 200, "success": true, "code": "S200", "message": "App settings retrieved",
  "data": {
    "dealership": {
      "name": "Bikaner Motors", "legal_name": "Bikaner Motors Private Limited", "tagline": "",
      "url": "https://www.BikanerMotors.com", "email": "", "phone": "", "address": "",
      "logo_url": "https://…/images/Logo-108x75.png", "favicon_url": null
    },
    "channels": { "mail": true, "sms": true, "whatsapp": true, "push": true },
    "account": {
      "editable_fields": ["gender", "email"],
      "can_change_display_name": true, "can_change_photo": true, "can_change_password": true
    },
    "ui": { "appearance_enabled": true, "menu_logo": "logo", "date_format": "d-M-Y", "time_format": "H:i" },
    "pricing_last_updated_at": "2026-09-30T10:12:00+05:30"
  }
}
```

| Field | Meaning |
|---|---|
| `dealership.*` | Settings → Site; `favicon_url` null = the built-in icon; `logo_url` falls back to the built-in logo |
| `channels.*` | Settings → Communication global switches (a switched-off channel is not sent; OTPs always are) |
| `account.editable_fields` | Personal fields the user may change themselves: `email`, `mobile`, `aadhaar_no`, `pan_no`, `dob`, `joining_date` (employees), `marital_status`, `gender` |
| `ui.*` | Appearance panel on / off, menu logo mode, site date / time formats (PHP format strings) |
| `pricing_last_updated_at` | Moves when published prices / masters change — re-download offline pricing data |

**401:** no or invalid token / device.

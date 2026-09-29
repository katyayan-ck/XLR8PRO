# API — Pricing (module PRC)

**Base URL:** `{{base_url}}/api/v1` (for example `https://crm.xceler8.in/api/v1`).
**Postman collection:** [`postman/pricing.postman_collection.json`](postman/pricing.postman_collection.json).
**Source:** `app/Http/Controllers/Api/V1/Vehicle/Pricing/PricingController.php` → `Engine\PricingQueryService::getPricing()`.
**Decisions:** DEC-073 (process), DEC-080 (the contract), DEC-081 (getPricing), DEC-083 (Loyalty, sync stamp).

## Common

**Authentication:**
- `Authorization: Bearer {token}`: a Sanctum token issued by `POST /auth/verify-otp`.
- The token carries the ability `device_id:{id}`, which must match a live device session (`validate_device`
  middleware).

**Envelope:** every response is JSON.

```json
{
  "http_status": 200,
  "success": true,
  "code": "S200",
  "message": "Pricing ready.",
  "timestamp": "2026-09-29T14:05:11+05:30",
  "data": { }
}
```

Errors carry `success: false`, a registered code (`App\Enums\ErrorCodeEnum`), and sometimes `errors` (field messages)
or `data`.

**Offline sync:**
- The app keeps vehicles, prices and accessories offline.
- It re-downloads them when `pricing.last_updated_at` moves (`GET /system-settings/category/pricing`, documented in
  `system-settings.md`).
- The stamp moves on any published-price, vehicle-master or accessory change (DEC-083).

---

## GET /vehicle/pricing/{oemCode} — published on-road pricing

The published price of one vehicle as the fixed-key **contract v2**, with the caller's selections applied. The totals
are recomputed the way Calculate & Publish does. Prices are the published snapshot; nothing is calculated from live
rules.

- **Route name:** `api.vehicle.pricing.show`
- **Middleware:** `auth:sanctum`, `validate_device`

### Path parameters
| Name | Type | Rules | Description |
|---|---|---|---|
| `oemCode` | string | required | Full OEM code **with** the colour suffix (e.g. `AZ1116YGTTA4EA01BZ`). Case and spaces are ignored. |

### Query parameters (all optional)
| Name | Type | Validation | Default | Meaning |
|---|---|---|---|---|
| `permit` | string | max 30 | the vehicle's own permit | Snapshot permit, e.g. `PRIVATE`; `PASSENGER` = the taxi price of a taxi-priced vehicle |
| `vin_type` | string | in `NV`, `OV` | `NV` | New VIN (current schemes) or old VIN (old schemes) |
| `channel` | string | in `normal`, `csd` | `normal` | Retail or CSD price |
| `wef_date` | date | date | today | The price valid on this date (WEF ≤ date, not expired) |
| `rsa_years` | int | 0–10 | the default (first paid 1-year) | RSA tenure; `0` = no RSA |
| `shield_scheme` | int | 0–10 | scheme 1 | Shield scheme number; `0` = none |
| `insurance[company]` | string | max 40 | the default company | Insurer code |
| `insurance[plan]` | string | max 20 | the company's first plan | Plan, e.g. `1+3` |
| `insurance[addons][]` | string[] | each max 40 | the default combo (add-on master) | Add-on codes, e.g. `NIL_DEP`, `KEY`; an empty list = base only |
| `reg_type` | string | in `Regular`, `BH` | `Regular` | `BH` uses the BH RTO option |
| `outside_state` | bool | boolean | false | Adds the Outside State TRC |
| `include_cod` | bool | boolean | the setting | Adds the COD charges |
| `exchange` | string | max 60 | none | Exchange scheme name (conditional discount) |
| `corporate` | string | max 60 | none | Corporate category |
| `loyalty` | string | max 60 | none | Loyalty scheme (DEC-083) |

An unknown selection (for example an RSA tenure that isn't offered) is **not** an error: it is ignored and listed in
`data.pricing.errors[]`.

### 200 — pricing ready

```json
{
  "http_status": 200, "success": true, "code": "S200", "message": "Pricing ready.", "timestamp": "…",
  "data": {
    "pricing": {
      "contract_version": 2, "source": "snapshot", "published_at": "2026-10-01T02:10:00+05:30",
      "oem_code": "AZ1116YGTTA4EA01BZ", "display_name": "Scorpio-N Z8", "custom_model": "Scorpio-N", "custom_variant": "Z8",
      "colour": "EVEREST WHITE", "segment": "PV", "sub_segment": "PV", "model_code": "SCORPIO-N", "price_list": "PV",
      "vehicle_permit": "PRIVATE", "permit": "PRIVATE", "rto_permit": "Private", "insu_permit": "Private",
      "fuel": "DIESEL", "taxi_price": "YES", "channel": "normal", "vin_type": "NV", "wef_date": "2026-10-01",
      "ex_showroom": 2276500.0, "assessable_value": 1557827.0, "gst_percent": 40.0, "gst_amount": 0.0, "mm_invoice": 0.0, "dealer_margin": 68245.0,
      "dealer_charges": { "incidental": 0.0, "fastag": 600.0, "trc": 1000.0, "rto_tape": 1299.0, "cod": 42000.0, "kazam": 0.0, "cod_in_total": false, "total": 2899.0, "rule_id": 12 },
      "rsa": { "selected_years": 1, "selected_amount": 2021.0, "standard_coverage": null, "options": [ { "years": 1, "name": "Std + 1 Year", "amount": 2021.0, "default": false } ] },
      "shield": { "selected_scheme": 1, "selected_amount": 8999.0, "standard_warranty": null, "options": [ { "scheme": 1, "name": "Shield Scheme 1", "amount": 8999.0, "default": true } ] },
      "accessories": { "amount": 30000.0, "discount": 30000.0, "items": [] },
      "discounts": {
        "oem_scheme": 75000.0, "dealer_cont": 25000.0, "consumer_scheme": 100000.0, "cash": 70000.0, "accessory": 30000.0,
        "shield": 0.0, "rsa": 0.0, "accessory_eligibility": 0.0, "shield_eligibility": 0.0, "total": 200000.0,
        "exchange": { "selected": null, "amount": 0.0, "options": [ { "scheme": "Scrappage", "oem": 10000.0, "dealer": 5000.0, "total": 15000.0 } ] },
        "corporate": { "selected": null, "amount": 0.0, "options": [ { "category": "CAT A", "oem": 3000.0, "dealer": 2000.0, "total": 5000.0 } ] },
        "loyalty": { "selected": null, "amount": 0.0, "options": [] }
      },
      "insurance": {
        "insu_permit": "Private",
        "default": { "company": "USGI", "plan": "1+3", "addons": ["NIL_DEP", "CONSUMABLES"], "od": 50609.0, "tp": 25228.0, "addons_total": 9732.0, "gst": 15402.0, "total": 100971.0, "frozen": true },
        "companies": [ { "company": "USGI", "default": true, "plans": [ { "plan": "1+3", "od": 50609.0, "tp": 25228.0, "gst": 13647.0, "addons": [ { "code": "NIL_DEP", "name": "Nil Depreciation", "premium": 7967.0, "default": true } ] } ] } ]
      },
      "rto": { "rto_permit": "Private", "reg_type": "Regular", "rule_id": 41, "base": 2277000.0, "tax": 273240.0, "surcharge": 34155.0, "hypothecation": 1500.0, "green_tax": 7500.0, "registration_fee": 600.0, "duplicate_tax_card": 100.0, "fitness": 0.0, "penalty": 0.0, "outside_state_trc": 1000.0, "total": 317095.0, "options": [] },
      "tcs": { "limit": 1000000.0, "rate": 1.0, "base": 2076500.0, "applicable": true, "amount": 20765.0 },
      "gross": 2729486.0, "invoice_value": 2097265.0, "on_road": 2550251.0,
      "withheld": { "rsa": 0.0, "shield": 0.0, "accessories": 0.0, "total": 0.0 },
      "hold": false, "errors": []
    }
  }
}
```

**Contract rules:**
- Every key is always present: unused amounts are `0.0`, lists `[]`, text `null`. Amounts are floats.
- `on_road` = `gross` + `tcs.amount` − `discounts.total`.
- `gross` = ex-showroom + `dealer_charges.total` + RSA + Shield + accessories + insurance + RTO.
- `discounts.total` = consumer scheme + cash + accessory + Shield + RSA, plus any selected exchange / corporate /
  loyalty.
- TCS = rate × (ex-showroom − discounts) when ex-showroom ≥ the limit.

### 401 — not signed in / no device
- No or an invalid token (DEC-085 envelope):

```json
{ "http_status": 401, "success": false, "code": "AUTH_UNAUTHORIZED", "message": "Unauthenticated.", "timestamp": "…" }
```

- A token without a live device session: the `validate_device` middleware answers with `E002` (renamed only with the
  app team, DEC-085):

```json
{ "http_status": 401, "success": false, "code": "E002", "message": "Unauthorized: Invalid device session" }
```

### 404 — no published price
```json
{ "http_status": 404, "success": false, "code": "PRICING_NOT_FOUND", "message": "No published price for AZ1116YGTTA4EA01BZ with these options.", "timestamp": "…" }
```
It means nothing is published for the code / permit / VIN type / channel on the date (a wrong code, a CSD channel
without a CSD price, or a date before the first WEF).

### 422 — invalid parameters
```json
{ "http_status": 422, "success": false, "code": "VALIDATION_FAILED", "message": "Validation failed", "timestamp": "…",
  "errors": { "vin_type": ["The selected vin type is invalid."] } }
```

### 423 — price list on hold
The price is returned so the app can show it, but it must not be quoted or booked.

```json
{ "http_status": 423, "success": false, "code": "PRICING_ON_HOLD", "message": "This price list is on hold — quotations and bookings are paused until it is reopened.", "timestamp": "…",
  "data": { "pricing": { "…": "the full contract as in 200", "hold": true } } }
```

### 500 — unexpected
```json
{ "http_status": 500, "success": false, "code": "SYSTEM_ERROR", "message": "An unexpected error occurred", "timestamp": "…" }
```

### Examples
- Default price: `GET /vehicle/pricing/AZ1116YGTTA4EA01BZ`
- Taxi price, 2-year RSA, BH, exchange:
  `GET /vehicle/pricing/AZ1116YGTTA4EA01BZ?permit=PASSENGER&rsa_years=2&reg_type=BH&exchange=Scrappage`
- A custom insurance combo:
  `GET /vehicle/pricing/AZ1116YGTTA4EA01BZ?insurance[company]=USGI&insurance[plan]=1%2B3&insurance[addons][]=NIL_DEP&insurance[addons][]=KEY`

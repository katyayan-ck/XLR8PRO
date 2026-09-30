# API — Vehicle compare (module VEH, DEC-092)

**Base URL:** `{{base_url}}/api/v1/vehicles/compare`.
**Postman collection:** [`postman/vehicles-compare.postman_collection.json`](postman/vehicles-compare.postman_collection.json).
**Source:** `app/Http/Controllers/Api/V1/Vehicle/CompareController.php` → `App\Services\Vehicle\Content\CompareService`
(the admin screen Vehicles → Compare Vehicles uses the same service).
**Auth:** Bearer token + a live device session ([auth.md](auth.md)) **and** permission `VEH_CMPR_VIEW`.
**Envelope and common errors:** [index.md](index.md#errors-common-to-every-endpoint-dec-085).

> Two comparisons: trims (variant codes) of **one model** side by side on their **features**, and models of **one
> segment** side by side on their **specifications**. 2–6 vehicles per call. Rows where no vehicle has a value are left
> out; `only_differences=1` also leaves out rows where every vehicle has the same value.

## GET /variants — trims of one model by features
**Route name:** `api.vehicles.compare.variants`.

| Query | Validation | Notes |
|---|---|---|
| `model` | required, string, max 50 | model code, e.g. `BOLERO-NEO` |
| `variants[]` | required, array, max 6; each string max 50 | variant codes of that model (codes of other models are ignored) |
| `only_differences` | boolean (optional) | `1` = differing rows only |

**200:**

```json
{
  "http_status": 200, "success": true, "code": "S200", "message": "Comparison ready",
  "data": {
    "kind": "variants",
    "model": { "code": "BOLERO-NEO", "name": "BOLERO NEO", "segment": "PV" },
    "columns": [ { "code": "AU36NPXZ7TX33C00", "name": "BOLERO NEO N10 BS6.2 - R - REFRESH" },
                 { "code": "AU36MPXZ7TX33C00", "name": "BOLERO NEO N10 OPT BS6.2 - REFRESH" } ],
    "groups": {
      "Comfort & Convenience": [
        { "label": "Arm Rest in 2nd Row", "values": { "AU36NPXZ7TX33C00": "Yes", "AU36MPXZ7TX33C00": "No" }, "differs": true }
      ]
    }
  }
}
```

`values` are `Yes`, `No`, a short text (e.g. `6 airbags`) or `null` (not recorded). **404** `RESOURCE_NOT_FOUND` — no
such model. **422** `VEHICLE_COMPARE_SELECTION` — fewer than 2 (or more than 6) trims of that model.

## GET /models — models of one segment by specifications
**Route name:** `api.vehicles.compare.models`.

| Query | Validation | Notes |
|---|---|---|
| `models[]` | required, array, max 6; each string max 50 | model codes of **one** segment |
| `only_differences` | boolean (optional) | `1` = differing rows only |

**200:** `data = {kind: "models", segment: "PV", columns: [{code, name}], groups: {category: [{label, values, differs}]}}` —
`label` is the specification name with its unit (e.g. `Power (hp)`); a value `N/A` means not applicable.

**422** `VEHICLE_COMPARE_SEGMENT` (models of different segments; `data.segments` lists them) or
`VEHICLE_COMPARE_SELECTION` (fewer than 2 or more than 6 models).

```json
{ "http_status": 422, "success": false, "code": "VEHICLE_COMPARE_SEGMENT",
  "message": "Only models of the same segment can be compared.", "data": { "segments": ["PV", "CV"] } }
```

**403** — the user lacks `VEH_CMPR_VIEW`. **401** — no or invalid token / device.

# Vehicle Accessory Catalog

**Functional Requirements Specification (FRS) & Developer Usage Guide**

| Field | Value |
|-------|-------|
| Module | Vehicle Accessories |
| Project | BMPL / XLR8 |
| Version | 2.0 |
| Date | 2026-08-01 |
| Audience | Product Owners, BA, Backend Developers, QA |

---

# PART A — Functional Requirements Specification

## 1. Purpose

Provide a single, typed catalog of vehicle accessories (mats, coatings, PPF, GPS VLTD, RTO tape, Kazam, etc.) that can be:

1. Imported from a multi-sheet Excel workbook
2. Scoped to **segment / model / variant / permit**
3. Fetched at runtime for a specific vehicle context, or as a full catalog listing

---

## 2. Scope

### 2.1 In scope

- Multi-sheet Excel import with **purge-and-reload** semantics
- Accessory types: `Accessory`, `Ceramic`, `PPF`, `Maxicare`, `GPS_VLTD`, `RTO_Tape`, `Kazam`
- Scope dimensions: **SEGMENT**, **MODEL**, **Variant**, **Permit** (Permit only where the sheet supplies it)
- **ANY** semantics at every scope level (blank / `ANY` / `ALL` → `NULL` in DB)
- Strict fetch contract: all four scope keys required; **all-ANY** or **all-concrete** only
- List by type bundle, single type, or vehicle+permit combo
- Export of flat accessory × scope rows
- Soft delete and **6 audit columns** on both tables (**no FK constraints**)

### 2.2 Out of scope

- Pricing engines, tax calculation, fitment charges (sheet may contain them; import ignores them)
- Stock / inventory / warehouse
- UI screens (this FRS covers service + data layer)
- Creating vehicle master data (segment / model / variant) — catalog only **references** existing codes

---

## 3. Actors

| Actor | Actions |
|-------|---------|
| Ops / Data Admin | Runs artisan import; validates warnings; re-imports after sheet updates |
| Backend Service | Calls `AccessoryService::list` / `listForVehicle` / `listByType` |
| Sales / DMS UI | Consumes list API for accessory picker on a vehicle+permit context |
| QA | Validates import counts, scope expansion, fetch contract rejection |

---

## 4. Data Model

### 4.1 `xlr8_vehicle_accessories` (master)

| Column | Type | Notes |
|--------|------|-------|
| `part_no` | varchar(25) UNIQUE | Business key |
| `type` | varchar(30) | `Accessory` \| `Ceramic` \| `PPF` \| `Maxicare` \| `GPS_VLTD` \| `RTO_Tape` \| `Kazam` |
| `item` | varchar(150) | Product name from **ITEM NAME** |
| `display_name` | varchar(150) NULL | Not imported in v2 (always null) |
| `ndp` | decimal(12,2) NULL | Not imported in v2 |
| `mrp` | decimal(12,2) | From **MRP (ROUNDED)**; missing → **0** |
| `set_qty` | int UNSIGNED | Default **1** (not imported) |
| `discount` | decimal(8,4) | From **Discount** column only; missing/invalid → **0** |
| `details` / `bundle` | — | Optional future use |
| `status` | tinyint | 1 = active, 0 = inactive |
| `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by` | audit | 6 audit columns + soft delete |

### 4.2 `xlr8_vehicle_accessory_scopes`

| Column | Type | Notes |
|--------|------|-------|
| `part_no` | varchar(25) | Soft ref to `accessories.part_no` (**no FK**) |
| `segment_code` | varchar(25) NULL | `NULL` = **ANY** |
| `model_code` | varchar(25) NULL | `NULL` = **ANY** |
| `variant_code` | varchar(50) NULL | `NULL` = **ANY** |
| `permit` | varchar(30) NULL | `Passenger` \| `Goods` \| … ; `NULL` = **ANY** |
| `status` | tinyint | 1 = active |
| audit cols | — | Same 6-column pattern |

**Unique key:** `(part_no, segment_code, model_code, variant_code, permit)`

> Multiple `NULL` combinations are allowed by MySQL unique semantics and are intentional for ANY scopes.

---

## 5. Functional Requirements

### FR-01 Multi-sheet import

- System **SHALL** purge all rows from both accessory tables before each import (hard delete).
- System **SHALL** map workbook sheets to types:

  | Sheet name | `type` |
  |------------|--------|
  | Accessories | `Accessory` |
  | Ceramic | `Ceramic` |
  | PPF | `PPF` |
  | Maxicare | `Maxicare` |
  | GPS VLTD | `GPS_VLTD` |
  | RTO Tape | `RTO_Tape` |
  | Kazam | `Kazam` |

- System **SHALL** process sheets by name when available, otherwise by fixed sheet order index.
- System **SHALL** skip rows with missing **PART NO.** or **ITEM NAME** and count them as skipped.
- System **SHALL** isolate per-row failures (try/catch) so one bad row does not abort the whole import.

### FR-02 Captured columns only

Import **SHALL** read **only** these columns:

| Column | Source header(s) | If missing |
|--------|------------------|------------|
| SEGMENT | `SEGMENT` | ANY (`NULL` scope) |
| MODEL | `MODEL` | ANY |
| Variant | `Variant` | ANY |
| Permit | `Permit` (GPS VLTD / RTO Tape only) | ANY |
| ITEM NAME | `ITEM NAME` | row **skipped** |
| PART NO. | `PART NO.` | row **skipped** |
| MRP | `MRP (ROUNDED)` **only** | **0** |
| Discount | `Discount` **only** | **0** |

- Import **SHALL NOT** fall back to `Retn %`, `NDP`, `Set Qty`, `Display Name`, or any other column.
- Permit is optional in the sheet; present only on GPS VLTD and RTO Tape. Blank → ANY.

### FR-03 Scope resolution & ANY

- Blank cell, `ANY`, or `ALL` on SEGMENT / MODEL / Variant / Permit → store `NULL` (ANY).
- Comma-separated MODEL (e.g. `"XUV700, XUV7XO"`) **SHALL** expand to one scope row per resolved code (cartesian product with other dimensions).
- SEGMENT names `PERSONAL` / `COMMERCIAL` map to codes `PV` / `CV`; `BEV` and `LMM` map to themselves.
- MODEL resolution uses `xlr8_vehicle_model.code` / `name` / `oem_name` only (**no `custom_name`** — column does not exist on live table).
- Unresolved MODEL labels → ANY (`NULL`) with optional warning; known segment names used as model do not warn.

### FR-04 Fetch contract (mandatory)

- `list()` **SHALL** require keys: `segment`, `model`, `variant`, `permit`.
- **Mode A:** all four = `ANY` → full catalog for requested type(s).
- **Mode B:** all four = concrete values → vehicle + permit combo.
- Any **mixed** combination **SHALL** throw `InvalidArgumentException`.
- Match rule: scope row matches if each dimension is **exact code OR `NULL` (ANY)**.

### FR-05 Type filters

| `type` filter | Result set |
|---------------|------------|
| `all` / `bundle` / (default) | Accessory + Ceramic + PPF + Maxicare + GPS_VLTD (**excludes** RTO_Tape, Kazam) |
| `OnlyAcc` / `Accessory` | `type = Accessory` only |
| `Ceramic` \| `PPF` \| `Maxicare` \| `GPS_VLTD` | That type only |
| `RTO_Tape` \| `Kazam` | That type only (**must be requested explicitly**) |

### FR-06 Hierarchy semantics

- Segment → Model → Variant is hierarchical in master data.
- **ANY at a parent level** means the accessory applies to all children under that parent when a concrete child is fetched (because `NULL` matches any concrete value).
- Example: scope `(PV, NULL, NULL, NULL)` matches fetch `(PV, XUV3XO, VAR1, Passenger)`.

---

## 6. Use Cases

### UC-01 Full catalog reload

1. Ops places `Accessories.xlsx` under `storage/`
2. Runs: `php artisan import:vehicle-accessories "storage/Accessories.xlsx"`
3. System purges tables, imports all sheets, prints totals / skipped / warnings
4. Ops reviews warnings (unknown models → ANY) and confirms row counts

### UC-02 Accessory picker for a sold vehicle

1. UI knows `segment=PERSONAL`, `model=XUV 3XO`, `variant=AX5…`, `permit=Passenger`
2. Backend calls `listForVehicle('PERSONAL', 'XUV 3XO', 'AX5…', 'Passenger')`
3. Service returns Accessory + Ceramic + PPF + Maxicare + GPS_VLTD rows that match or are ANY on each dimension
4. UI displays `type`, `item`, `part_no`, `mrp`, `discount`

### UC-03 RTO Tape for commercial goods permit

1. Mixed ANY is **invalid** — do not call with some ANY and some concrete.
2. Correct options:
   - All concrete: `listByType('RTO_Tape', 'COMMERCIAL', 'VEERO', $variantCode, 'Goods')`
   - All ANY with type filter: `list(['type'=>'RTO_Tape', 'segment'=>'ANY', 'model'=>'ANY', 'variant'=>'ANY', 'permit'=>'ANY'])`
3. For specials in a real vehicle context, prefer concrete vehicle + `permit=Goods`.

### UC-04 Catalog-wide admin listing

1. Admin calls `list(['type'=>'all', 'segment'=>'ANY', 'model'=>'ANY', 'variant'=>'ANY', 'permit'=>'ANY'])`
2. Returns full bundle (**no** RTO_Tape / Kazam)

### UC-05 Export for audit

1. `exportRows()` produces one row per accessory × scope with TYPE, SEGMENT, MODEL, Variant, Permit, ITEM, PART, MRP, Discount, STATUS

---

## 7. Edge Cases & Business Rules

| Case | Expected behaviour |
|------|-------------------|
| Discount column empty / formula (`=J10/I10`) | Store **0** (never strip digits from formulas) |
| MRP (ROUNDED) empty | Store **0** |
| MODEL = `"BEV"` or `"PERSONAL"` | Treat as ANY; no noisy warning |
| MODEL = `"XUV 9S"` not in master | ANY + warning |
| MODEL = `"BE 6"` / `"XUV 3X0"` | Alias map → `BE6` / `XUV 3XO` |
| MODEL = `"7XO, ROXX, SCORPIO"` | Expand to multiple model scope rows |
| Duplicate `part_no` across sheets | Last write wins (`updateOrCreate` by `part_no`); type from last sheet processed |
| Same `part_no`, multiple models | Multiple scope rows, one master row |
| Maxicare sheet empty | No rows; no error |
| PPF row with empty NDP/MRP | MRP=0; still imported if part+item present |
| Fetch missing `permit` key | `InvalidArgumentException` |
| Fetch `segment=PV, model=ANY, variant=X, permit=Y` | **REJECT** (mixed) |
| Fetch all ANY | **ALLOW** — full type filter set |
| Scope `NULL` permit vs fetch `Passenger` | Matches (ANY) |
| Scope `permit=Goods` vs fetch `Passenger` | Does not match |
| Soft-deleted accessory | Excluded when `active_only=true` (default) |
| Import mid-failure on one row | Row logged in `errors[]`; other rows commit |

---

## 8. Non-Functional

- Import is transactional at the batch level with per-row isolation for recoverable errors.
- **No foreign keys**; code soft-references only.
- Import log written to `ImportLog` when model exists (best-effort).
- Logging: warnings for unresolved models; errors for exceptions.

---

# PART B — Developer Usage Guide

## 9. File map

| Path | Role |
|------|------|
| `app/Models/Vehicle/Accessory.php` | Master model + type constants |
| `app/Models/Vehicle/AccessoryScope.php` | Scope model |
| `app/Services/Vehicle/AccessoryService.php` | Import / list / export |
| `app/Services/Vehicle/AccessoryImportService.php` | Thin compat wrapper |
| `app/Console/Commands/ImportVehicleAccessories.php` | Artisan command |
| `database/migrations/2026_07_31_000001_recreate_vehicle_accessories_tables.php` | Drop + create tables |

---

## 10. Schema setup

**Preferred — migration:**

```bash
php artisan migrate --path=database/migrations/2026_07_31_000001_recreate_vehicle_accessories_tables.php
```

**Or** execute `sql_accessory_schema_update.sql` (DROP + CREATE, no FKs, 6 audit cols).

---

## 11. Import

### 11.1 Command

```bash
php artisan import:vehicle-accessories "storage/Accessories.xlsx"
php artisan import:vehicle-accessories "storage/Accessories.xlsx" --user=1
```

Always **purges** existing catalog then reloads. Safe to re-run.

### 11.2 Sheet → type order

(Fallback when sheet titles are numeric indices from Maatwebsite)

| Index | Sheet name | `type` |
|-------|------------|--------|
| 0 | Accessories | `Accessory` |
| 1 | Maxicare | `Maxicare` |
| 2 | Ceramic | `Ceramic` |
| 3 | PPF | `PPF` |
| 4 | GPS VLTD | `GPS_VLTD` |
| 5 | RTO Tape | `RTO_Tape` |
| 6 | Kazam | `Kazam` |

### 11.3 Programmatic import

```php
use App\Services\Vehicle\AccessoryService;

$result = app(AccessoryService::class)
    ->importExcelWithSheetOrder(storage_path('Accessories.xlsx'), auth()->id() ?? 1);

// $result keys:
//   success, message, total_records, imported_count,
//   skipped_count, errors_count, warnings, errors
```

---

## 12. Fetch API

### 12.1 Contract

Keys `segment`, `model`, `variant`, `permit` are **MANDATORY**.  
Values may be concrete codes/names or `ANY` / `ALL` / `*`.

| segment | model | variant | permit | Result |
|---------|-------|---------|--------|--------|
| ANY | ANY | ANY | ANY | **OK** — full catalog for type filter |
| PERSONAL | XUV 3XO | AX5… | Passenger | **OK** — vehicle combo |
| PERSONAL | ANY | AX5… | Passenger | **REJECT** — mixed |
| PV | XUV3XO | ANY | Passenger | **REJECT** — mixed |
| *(missing key)* | — | — | — | **REJECT** — missing key |

### 12.2 `listForVehicle` (bundle)

```php
$rows = app(AccessoryService::class)->listForVehicle(
    'PERSONAL',          // or PV
    'XUV 3XO',           // resolved via aliases / DB
    'AW62BBZF7TF11D00',  // variant code
    'Passenger'
);
// Excludes RTO_Tape and Kazam
```

### 12.3 `listByType`

```php
$svc = app(AccessoryService::class);

// Standard accessories only
$svc->listByType('Accessory', 'PV', 'BE6', $variantCode, 'Passenger');

// Ceramic only
$svc->listByType('Ceramic', 'BEV', 'BE6', $variantCode, 'Passenger');

// Specials — must request explicitly (all concrete)
$svc->listByType('RTO_Tape', 'CV', 'VEERO', $variantCode, 'Goods');

// Type-wide specials — all ANY
$svc->list([
    'type'    => 'Kazam',
    'segment' => 'ANY',
    'model'   => 'ANY',
    'variant' => 'ANY',
    'permit'  => 'ANY',
]);
```

### 12.4 Response shape

```json
[
  {
    "type": "Accessory",
    "part_no": "BE50009",
    "item": "3D Boot Mat",
    "display_name": null,
    "ndp": null,
    "mrp": 2549.0,
    "set_qty": 1,
    "discount": 0.0,
    "segment_code": "PV",
    "model_code": "BE6",
    "variant_code": null,
    "permit": null,
    "status": 1
  }
]
```

---

## 13. Model resolution aliases

Excel labels are normalized against `xlr8_vehicle_model`. Built-in aliases:

| Excel label | Resolved code |
|-------------|---------------|
| BE 6 / BE6 | BE6 |
| XUV 3X0 / XUV3XO | XUV 3XO |
| XUV 700 / XUV700 | XUV700 |
| XUV 7XO | XUV7XO |
| THAR ROXX | THAR ROXX |
| SCORPIO N / SCORPIO-N | SCORPIO-N |
| SCORPIO CLASSIC | SCORPIO |
| XEV 9e / XEV9E | XEV9E |

If still unresolved: match `UPPER(REPLACE(code,' ',''))` then `name` / `oem_name`. Failure → ANY.

---

## 14. Segment aliases

| Excel / input | `segment_code` |
|---------------|----------------|
| PERSONAL / PV | PV |
| COMMERCIAL / CV | CV |
| BEV | BEV |
| LMM | LMM |
| ANY / ALL / blank | NULL (ANY) |

---

## 15. Testing checklist (QA)

1. After migrate: both tables empty, structure matches FRS §4
2. Import `Accessories.xlsx`: imported ≈ non-empty rows; skipped = blank part/item
3. Spot-check part `BE50009`: type Accessory, mrp 2549, model_code BE6, segment PV
4. Spot-check GPS `BMT0066`: type GPS_VLTD, permit Passenger
5. Spot-check RTO `AS585`: type RTO_Tape, permit Goods
6. Spot-check Kazam: type Kazam; **not** returned by `listForVehicle`
7. `list` all-ANY returns bundle without RTO_Tape / Kazam
8. `list` mixed ANY throws `InvalidArgumentException`
9. `list` missing key throws `InvalidArgumentException`
10. Discount empty in sheet → `discount = 0` in DB
11. Re-import is idempotent (purge + reload)

---

## 16. Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Unknown column `custom_name` | Old matcher vs live model schema | Use latest `AccessoryService` (`code`/`name`/`oem_name` only) |
| discount 1010/1111 warnings | Formula cells stripped to digits | `parseDiscountOnly` rejects formulas → 0 |
| Only 4 rows imported | Exception aborted early transaction | Per-row try/catch in current service |
| Pivot table errors | Old importer design | Scopes go to `xlr8_vehicle_accessory_scopes` only |
| Empty primary fields in UI | Wrong fetch contract / mixed ANY | Pass all four concrete or all ANY |
| XUV 9S always ANY | Not in `xlr8_vehicle_model` | Add model master row or accept ANY |

---

## 17. Module changelog

| Ver | Date | Notes |
|-----|------|-------|
| 1.0 | 2026-05 | Initial accessories + scopes; pivot-style experiments |
| 2.0 | 2026-08-01 | Typed multi-sheet catalog; purge import; strict fetch contract; Discount/MRP only; no FK; 6 audit cols |

---

## 18. Related modules

- Vehicle masters: `xlr8_vehicle_segment`, `xlr8_vehicle_model`, `xlr8_vehicle_variant`
- Vehicle define / info importers (do **not** modify accessory tables)
- IAM / person services — unrelated; do not couple

---

*End of document. Keep this FRS in sync with `AccessoryService` and laradocs when behaviour changes.*

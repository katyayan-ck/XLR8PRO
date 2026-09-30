# Vehicle — segments, sub segments, models, variants (one row per colour), accessories

The vehicle master tree **Segment → Sub segment → Model → Variant**. A variant row is one **OEM code = one colour**
(DEC-048): `xlr8_vehicle_variant.code` is the full OEM code, `color` / `color_code` are on the same row. Codes are the
keys everywhere (`model_code`, `variant_code`). Pricing lives on top of this — see [pricing.md](pricing.md).

| Need | Use |
|---|---|
| dependent dropdowns segment → model → variant → colour | `VehicleService::segmentOptions()`, `modelOptionsFor($seg)`, `variantOptions($model)`, `colorOptions($variant)` |
| variants of a level | `VehicleService::variantsOf($seg, $sub, $model, 'ACTIVE'|'INACTIVE'|'ALL')`, `descendantsOf('MODEL', $code)` |
| find by OEM code | `VehicleService::findByOemCode($code)` |
| is the variant ready for pricing / sales | `VehicleService::isComplete($variant)` / `missingFields($variant)` |
| create / edit masters | `Vehicle\{Segment,SubSegment,VehicleModel,Variant}Service` (entity services) |
| accessories for a vehicle | `AccessoryService::listForVehicle($seg, $model, $variant, $permit)` — full guide `tech-guides/frs-and-workflows/frs/accessory-catalog-frs.md` |

**Status (four values, KeyValue `status_id`):** `INCOMPLETE` (fresh price-list stub, not sellable) · `ACTIVE` (complete
and on sale — the only state with `is_active = true`) · `INACTIVE` (taken off sale) · `DISCONTINUED`. Detection never
creates `INACTIVE`. **Only a complete vehicle may be Active — enforced by `VariantService` on every write** (screen,
import, service; DEC-073). New variants start inactive; `wheels` / `taxi_price` have no DB default. `VEHICLE_STATUS` holds
all four codes. Free-text model names are matched through
the canonical hyphenated code (`modelCodeCandidates()` below).

**Local data note:** the local `xlrm` vehicle masters were purged for a fresh import (DEC-051); `xlrm_testing` still has
them. Try examples on the test copy.

---

## Models

| Model | Table | Key | Relations / helpers | Writer |
|---|---|---|---|---|
| `Vehicle\Segment` | `xlr8_vehicle_segment` | `code` (≤ 5, e.g. PV, CV, BEV) | `subSegments()`, `vehicleModels()`, `variants()`; `HasCommunications`; `static generateCode($name)` | `SegmentService` |
| `Vehicle\SubSegment` | `xlr8_vehicle_subsegment` | `code` (≤ 20), parent `segment_code` | `segment()`, `vehicleModels()`, `variants()`; `static generateCode($oemName)` | `SubSegmentService` |
| `Vehicle\VehicleModel` | `xlr8_vehicle_model` | `code` (≤ 30, hyphenated OEM stem `THAR-ROXX`, DEC-049) | `segment()`, `subSegment()`, `variants()`, `colors()` (legacy), `brand()` (legacy column); `name`, `oem_name` (unique), `custom_name`; `static generateCode($customName)` | `VehicleModelService` |
| `Vehicle\Variant` | `xlr8_vehicle_variant` | `code` = full OEM code (≤ 40), unique per `color_code` | `segment()`, `subSegment()`, `vehicleModel()`; KeyValue links `permit()`, `fuelType()`, `bodyType()`, `bodyMake()`, `statusKkv()`; option lists `getPermitOptions()`, `getFuelTypeOptions()`, `getBodyTypeOptions()`, `getBodyMakeOptions()`, `getStatusOptions()` (via `KeywordValueService`); `static codeFromModelCode('BM12AH515MB01D00JD')` → `BM12AH515MB01D00` | `VariantService` |
| `Vehicle\Color` | `xlr8_vehicle_color` | — | **retired** (DEC-060): colours now come from variant rows; `static codeFromModelCode()` → last 2 chars | none (read-only legacy) |
| `Vehicle\Brand` | `xlr8_vehicle_brand` | `code` | `segments()`, `vehicleModels()`, `variants()` by `brand_code` (columns not on those tables any more — legacy); `generateCode()` | legacy |
| `Vehicle\Accessory` | `xlr8_vehicle_accessories` | `part_no` | `scopes()`; scope `active()`; `static getAccessories($seg, $model, $variant)`; types `Accessory`, `Ceramic`, `PPF`, `Maxicare`, `GPS_VLTD`, `RTO_Tape`, `Kazam` (`BUNDLE_TYPES` excludes the last two) | `AccessoryService` / importers |
| `Vehicle\AccessoryScope` | `xlr8_vehicle_accessory_scopes` | (`part_no`, segment / model / variant) | `accessory()`, `segment()`, `model()`, `variant()`; scope `active()`; NULL level = ANY | importers |

**Variant columns:** `segment_code`, `sub_segment_code`, `model_code`, `code`, `color`, `color_code`, `oem_name`,
`custom_name`, `display_name`, `permit_id`, `taxi_price` (YES / NO), `fuel_type_id`, `seating_capacity`, `wheels`, `gvw`,
`cc_capacity`, `transmission`, `drivetrain`, `motor`, `gst_percent`, `shield_pack`, `is_csd`, `csd_index`,
`body_type_id`, `body_make_id`, `status_id`, `is_active`.

---

## VehicleService (`App\Services\Vehicle\VehicleService`)
Inject or `app(VehicleService::class)`. All writes go through the entity services below.

### Reads and dropdowns
| Method | Returns |
|---|---|
| `segments()` | active `Segment` rows (Collection) |
| `subSegments(?$segmentCode)` / `models(?$segmentCode, ?$subSegmentCode)` | active rows |
| `variantsOf(?$seg, ?$sub, ?$model, $status = 'ACTIVE')` | `Variant` rows; status `ACTIVE`, `INACTIVE` or `ALL` (constants `STATUS_*`), ordered by model, code |
| `descendantsOf('SEGMENT'\|'SUBSEGMENT'\|'MODEL', $code, $status)` | variants under that node |
| `segmentOptions()` | rows with `code`, `name` (DEC-060 dropdown source) |
| `modelOptions(?$segmentCode, $activeOnly = true)` | models of a segment (all when null) |
| `modelOptionsFor(?$segmentCode)` | active models of exactly that segment; **empty** when none chosen (dependent dropdowns) |
| `variantOptions(?$modelCode)` | `id, code, name` (custom name), `seating_capacity` |
| `variantGroupOptions(?$modelCode)` | one row per OEM variant (variant rows are per colour): `code` (a representative colour row — pass to `colorOptions()`), `name` (display / custom / OEM name); used by the quotation picker (DEC-082) |
| `colorOptions(?$variantCode)` | sibling colour rows of the variant: `code` (colour code), `name` (colour), `variant_code` |
| `colorsOfVariant($oemCode)`, `colorsOfModel($oemModel)`, `colorsOfSegment($seg, ?$sub)` | colour rows for a level |
| `findByOemCode($oemCode)` | `?Variant` |
| `findModel($name)` | `?VehicleModel` by canonical / spaced / compact code, custom name or OEM name — never creates ("Bolero Neo +" → BOLERO-NEO-PLUS; memoised per instance, DEC-077) |

### Completeness — `App\Services\Vehicle\VehicleCompleteness` (the single rule, DEC-073)
`missing(Variant $v): list<string>` (attribute keys), `missingLabels($v)` (Vehicle Info column names), `isComplete($v)`,
`permitCode($v)`, `isElectric($fuelCode)`. `VehicleService::isComplete()` / `missingFields()` delegate to it.
Always required: Segment, Sub Segment, Fuel, Seating, Wheels, Transmission, Drivetrain, Body Make, Body Type, GST%,
Permit, Taxi Price, Custom Model (model name), Custom Variant, Display Name, Colour Name. Then: **Private + ICE → CC;
Private + EV → Motor; Goods → GVW; Passenger and Misc → none** (user decision 28-09; amends spec v3.1.1 §3.3).
Helpers `permitCode($v)`, `fuelCode($v)` on VehicleService (KeyValue codes).
`VariantService` field formats (all write paths): `gst_percent` is stored as a percent — a fraction from the OEM sheets
(0.28) becomes 28; `transmission` "At" / "AT" → Automatic, "Mt" / "MT" → Manual (DEC-073).

```php
$missing = app(VehicleService::class)->missingFields($variant);   // ['gvw', 'body_type_id'] → show on the completeness screen
```

### Import helpers (price list / Vehicle Info)
| Method | Returns |
|---|---|
| `norm($v)` | trimmed upper-case string |
| `colorFromOemCode($oemCode)` | last 2 characters |
| `segmentFromSheetTitle('Price List LMM TZU')` | `LMM` |
| `findOrCreateSegment($code, ?$name, ?$userId)` / `findOrCreateSubSegment($seg, ?$code, ?$name, ?$userId)` | the row (created through the entity service when missing) |
| `findOrCreateModel($oemModel, $seg, ?$sub, ?$userId)` | the model; code in the hyphenated format |
| `modelCodeCandidates($oemModel)` | `['THAR-ROXX', 'THAR ROXX', 'THARROXX']` — canonical first, legacy spellings after |
| `createStubFromPriceList($oemCode, $oemModel, $oemVariant, $sheetTitle, ?$userId)` | `['variant' => Variant, 'created' => bool, 'model' => VehicleModel]` — creates a stub only when the OEM code is new: code, OEM names, colour code (LMM TZU → `NA`), status INCOMPLETE, inactive; colour name / custom variant / taxi flag left for Vehicle Info (DEC-073) |
| `applyVehicleInfo(Variant $v, array $row, ?$userId)` | `['complete' => bool, 'missing' => [...], 'active' => bool, 'variant' => Variant]`; a value that breaks a field rule throws `ValidationException` (the importer reports the row). Status column: ACTIVE on an incomplete vehicle → stays INCOMPLETE; INACTIVE / DISCONTINUED allowed; blank → ACTIVE / INACTIVE kept when complete, else INCOMPLETE |
| `statusCounts()` | `['ACTIVE' => n, 'INACTIVE' => n, 'INCOMPLETE' => n, 'DISCONTINUED' => n]` over the whole master (rows without a status count as INCOMPLETE) |
| `kkvId($keyword, $value, $create = false)` | KeyValue id for a label (creates through `KeyvalueService` when `$create`) |
| `copySpecifications($fromOem, $toOem, ?$userId)` | the updated target variant, or null when either is missing |

## Entity services
| Service | Fields (required*) | Rules |
|---|---|---|
| `SegmentService` | `code`* (≤ 5, immutable), `name`*, `is_active` | |
| `SubSegmentService` | `segment_code`*, `code`* (≤ 20), `name`*, `is_active` | |
| `VehicleModelService` | `segment_code`*, `sub_segment_code`, `code`* (≤ 30, immutable, hyphenated), `name`*, `oem_name` (unique), `is_active` | |
| `VariantService` | `segment_code`*, `sub_segment_code`, `model_code`*, `code`* (full OEM code ≤ 40), `color`, `color_code`, `oem_name`*, `custom_name`, `display_name`, `taxi_price`, KeyValue ids (`permit_id`, `fuel_type_id`, `body_type_id`, `body_make_id`, `status_id`), `seating_capacity`, `wheels`, `gvw`, `cc_capacity`, `transmission`, `drivetrain`, `motor`, `gst_percent`, `shield_pack`, `is_csd`, `csd_index`, `is_active` | `code` unique per `color_code`; segment / sub segment must match the variant's model |

## Accessories
| Class / method | Returns |
|---|---|
| `AccessoryService::list(['type' => …, 'segment', 'model', 'variant', 'permit', 'active_only'])` | rows `type, part_no, item, display_name, ndp, mrp, …`. **All four** scope keys are required and must be either all `ANY` (catalogue) or all concrete (one vehicle + permit `Passenger`/`Goods`) — mixed throws `InvalidArgumentException` |
| `listForVehicle($seg, $model, $variant, $permit)` | the full bundle for a vehicle (excludes `RTO_Tape`, `Kazam`) |
| `listByType($type, $seg, $model, $variant, $permit)` | one type (use for `RTO_Tape` / `Kazam`) |
| `exportRows($filters, $activeFirst)` | flat rows, one per accessory × scope |
| `importExcelWithSheetOrder($path, $userId)` | **the authoritative importer (DEC-083, BUG-179)**: typed sheets in order Accessories, Maxicare, Ceramic, PPF, GPS VLTD, RTO Tape, Kazam; **purges and reloads** the catalogue + scopes; `['success', 'message', 'total_records', 'imported_count', 'skipped_count', 'errors_count', 'warnings', 'errors']`. `importExcel()` matches sheets by title only (spreadsheet readers hand sheets over by position, so prefer the ordered one) |
| `Accessories\AccessoryItemService` / `Accessories\AccessoryScopeService` | entity services (write path) of the Accessories / Accessory Scopes masters (Admin → Pricing, `PRC_ACCS_*`) |
| `AccessoryExportService::rows($activeFirst, $filters)` / `store(?$path, $filters, $activeFirst, ?$userId, $disk)` / `markDownloaded()` | export rows / an xlsx on disk (`['path', 'url', …]`) |

The one-sheet `import:vehicle-accessories` command / `AccessoryImportService` / `VehicleAccessoriesImport` were retired (DEC-083, BUG-179). Accessory changes bump the app sync stamp (`pricing.last_updated_at`) and never recalculate prices. `AccessoryScope::$fillable` now includes `permit` (BUG-204).

## Use cases
**Cascading vehicle picker on a form**
```php
$svc = app(VehicleService::class);
$segments = $svc->segmentOptions()->pluck('name', 'code');
$models   = $svc->modelOptionsFor($request->segment)->pluck('name', 'code');      // AJAX
$variants = $svc->variantOptions($request->model)->pluck('name', 'code');
$colours  = $svc->colorOptions($request->variant)->pluck('name', 'code');
```

**Import a new variant from the OEM price list** — `Pricing\Import\PriceListDetectService` (pricing step 2) calls
`createStubFromPriceList()`; the Vehicle Info import then fills specs through `applyVehicleInfo()`.

**Accessories on a quotation**
```php
$bundle = app(AccessoryService::class)->listForVehicle($variant->segment_code, $variant->model_code, $variant->code, 'Passenger');
```

## Gotchas
- One variant row per colour: "the variant" in the UI is usually a group of rows sharing everything but `color_code`.
- Model codes are hyphenated (`THAR-ROXX`); older rows may still hold `THAR ROXX` — use `modelCodeCandidates()` when matching.
- The colour table is retired; don't read `xlr8_vehicle_color` in new code.
- `OrgService::variants()` / `models()` also exist (cached arrays); prefer `VehicleService` in vehicle screens; `OrgService::variantName($code)`
  returns the variant's display name.

## Testing
Run against `xlrm_testing` (vehicle rows exist there). Create test variants through `VariantService` inside the
transaction; assert `missingFields()` for completeness rules.

## Vehicle content — specifications, features, galleries (DEC-092)
- **Masters:** `SpecItem` (`xlr8_vehicle_spec_item`: code, category, name, unit, sort, is_active) and `FeatureItem`
  (`xlr8_vehicle_feature_item`: code, feature_group, name, sort, is_active) via `SpecItemService` / `FeatureItemService`
  — a missing code is derived from group + name (`ENGINE_DISPLACEMENT`, `…_2` when taken; trait `DerivesItemCode`).
- **Values:** `ModelSpec` (`xlr8_vehicle_model_spec`, one per model + item; `ModelSpecService::normaliseValue()`: `-`,
  `-NA-`, `NA` → `N/A`, blank → null) and `TrimFeature` (`xlr8_vehicle_trim_feature`, one per variant code + item;
  `TrimFeatureService::normaliseValue()`: Yes / Y / ✓ → `Yes`, No / N / `---` → `No`, other text kept). `upsert()` matches
  on the pair.
- **Trim:** `VehicleTrim` (`xlr8_vehicle_trim`, one per variant code — our variant rows are one per colour) via
  `VehicleTrimService::forVariant(string $variantCode): ?VehicleTrim` (created from the first colour row on first use).
- **Media** (public disk, `preview` 250 px + `thumb` 100 px): `VehicleModel` `images` (many) + `brochure` (single PDF);
  `VehicleTrim` `gallery` (all colours); `Variant` `gallery` (that colour only). A colour shows its own + the trim's images.
- **Relations:** `VehicleModel::specs()`, `ModelSpec::item()`, `TrimFeature::item()`, `VehicleTrim::features()`.
- **Permissions:** `VEH_CONT_VIEW`, `VEH_CONT_EDIT`, `VEH_CMPR_VIEW` (migration `2026_09_30_221447_create_vehicle_content_tables_dec092`).
- **Screens (Phase 2):** Vehicles → Vehicle Content (`Admin\Vehicle\Content\VehicleContentController`, routes
  `vehicle.content.*`): index (models by segment with what they have) → model page (tabs Specifications / Images &
  brochure / Trims) → trim page (tabs Features / Gallery with the level choice "all colours" or one colour).
  `VEH_CONT_VIEW` to see, `VEH_CONT_EDIT` to change.
- **`VehicleContentService`:** `overview()`, `modelSheet(VehicleModel)`, `saveModelSpecs(VehicleModel, array): int`,
  `addSpecItem()`, `addFeatureItem()`, `addModelImages()`, `setBrochure()`, `removeModelMedia(VehicleModel, int): Result`,
  `trimSheet(VehicleTrim)`, `saveTrimFeatures(VehicleTrim, array): int`, `addGalleryImages(VehicleTrim, string $level, array): Result`
  (`trim` or a colour code), `removeGalleryImage(VehicleTrim, int): Result` (owner checked), `trim(string): ?VehicleTrim`.
  Screen saves: blank clears; a file the collection refuses is a validation error on the upload field.

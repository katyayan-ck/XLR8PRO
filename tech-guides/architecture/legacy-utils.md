# Shared helpers — KeyValue, synonyms, identifiers, system settings, core legacy models

Small shared services that every module uses, plus the legacy settings stack and a few old core models. The platform
utilities (Settings, Notify, Chat, Docs, …) are in `tech-guides/platform/`.

| Need | Use |
|---|---|
| an enum / dropdown list (fuel types, permits, task types, sources …) | `KeywordValueService::getEnum('FUEL_TYPE')` |
| the id of one value (for an `_id` column) | `KeywordValueService::getValueId('PERMIT', 'PASSENGER')` |
| add / edit keyword values | `Utils\KeyvalueService`, `Utils\KeywordMasterService` (entity services) |
| map a typed / imported spelling to the canonical code | `SynonymService::resolve('Branch', 'Beekaner')` → `BKN` |
| normalise mobile / PAN / Aadhaar / TAN / GSTIN / chassis | `IdentifierService` (+ `App\Rules\*` for validation) |
| site date display | `DateFormatService`, `site_date()` ([core.md](core.md)) |
| runtime settings | the **Settings** platform service (tech-guides/platform/01) — `SystemSettingService` is legacy |

---

## KeyValue (lookup lists)
**Tables:** `xlr8_utils_keyword_master` (the list: `code`, `keyword`, `is_recursive`) and `xlr8_utils_keyvalue` (its
values: `keyword_code`, `code`, `key`, `value` (label), `details`, `parent_id` — **comma-separated** parent ids, `level`,
`path`, `extra_data`, `status`, `is_active`).

### KeywordValueService (`App\Services\KeywordValueService`, static, cached) — the only way to read
| Method | Returns |
|---|---|
| `getEnum($keywordCode, $activeOnly = true)` | `['PETROL' => 'Petrol', 'DIESEL' => 'Diesel', …]` code ⇒ label |
| `getValueId($keywordCode, $valueCode, $activeOnly = true)` | `?int` id (what `*_id` columns store) |
| `getValue($keywordCode, $valueCode, $activeOnly)` / `getByCode(...)` | `?Keyvalue` |
| `getCode($keywordCode, $valueCode, $activeOnly)` | the stored code (case-normalised) or null |
| `clearCache(?$keywordCode = null)` | forget one list or all |
```php
<x-ui.select name="fuel" :options="KeywordValueService::getEnum('FUEL_TYPE')" placeholder="Fuel" />
$variantData['permit_id'] = KeywordValueService::getValueId('PERMIT', 'PASSENGER');
```
Children of a parent value: `OrgService::keywordValueByParentCode($keyword, $parentCode, $parentKeyword)`
([org.md](../modules/org.md)).

### Writers (entity services)
| Service | Fields | Notes |
|---|---|---|
| `Utils\KeywordMasterService` | `code`* (≥ 3, ≤ 50, immutable), `keyword`* (unique), `description`, `details`, `extra_data`, `status` (1/0), `is_recursive`, `is_active` | |
| `Utils\KeyvalueService` | `keyword_code`* (existing, immutable), `code`* (≤ 150, unique within keyword, immutable; blank → taken from `key`), `key`, `value`, `details`, `parent_id` (comma ids), `level`, `path`, `extra_data`, `status`, `is_active` | `addParent(Keyvalue $v, $parentId)` appends a parent |

Always add values through a **migration** that calls the service (see
`2026_09_28_140000_platform_entity_actions_keyword` for the pattern), then `KeywordValueService::clearCache($code)`.

**Models:** `Keyvalue` — `keywordMaster()`, `parent()` / `children()` (single-id legacy relations; `parent_id` may hold
several ids), scopes `active()`, `forKeyword($code)`, `status_text`. `KeywordMaster` — `keyvalues()`, scopes
`recursive()`, `nonRecursive()`, `status_text`.

## SynonymService (`App\Services\Utils\SynonymService`, table `xlr8_utils_synonyms`)
| Method | Returns |
|---|---|
| `getSynonym($entityType, $value)` | the canonical value when `$value` is a known synonym or a canonical itself (case-insensitive); else null |
| `resolve($entityType, $value)` | canonical or the trimmed input unchanged — **call this before matching imported text** |
| `setSynonym($entityType, $canonical, 'BEEKANER,BIKANER', 'ADD'\|'REPLACE'\|'REMOVE', $userId)` | number of rows changed; `InvalidArgumentException` when type / canonical empty |
| `mapForType($entityType)` | `['BEEKANER' => 'BKN', …]` (cached) |
| `forgetCache(?$entityType)` | |
Model `Synonym`: scopes `active()`, `ofType($type)`, `canonical($value)`. Entity types in use: `Branch`, `Location`,
`Segment`, `Model`, `Variant`, `Permit`, … (whatever importers pass). Pricing importers resolve every scope cell through it.

## IdentifierService (`App\Services\IdentifierService`) — normalise, don't validate
| Method | Example |
|---|---|
| `cleanMobile($raw)` | `'+91 98290-12345'` → `'9829012345'`; not 10 digits → null |
| `normalizePan($raw)` / `normalizeTan($raw)` / `normalizeGstin($raw)` | trimmed upper-case |
| `normalizeAadhaar($raw)` | spaces / dashes removed |
| `normalizeChassis($raw)` | trimmed upper-case |
Validation rules: `App\Rules\PanNumber`, `TanNumber`, `AadhaarNumber`, `Gstin`, `ChassisNumber`, and friends. Entity
`Field::phone()` uses `cleanMobile()` automatically.

## Legacy settings stack
Both stacks use the same table `xlr8_utils_system_setting` (+ `_audit`).
- **New code: `Settings` facade** (tech-guides/platform/01-settings.md) — typed, scoped, audited, cached with bust on write.
- `SystemSettingService` (legacy, backs the old Settings CRUD screen and `DateFormatService`): `get($key, $default)`,
  `all()`, `allByTopic()`, `topic($t)`, `set($key, $value, ?$topic)`, `setMultiple($arr)`, `getForAdmin(?$topic)`,
  `getTopics()`, `getByGroup($topic, $group)`, `getAsEnv()`, `getSiteSettings()`, `getDealershipSettings()`,
  `getPricingSettings()`, `getSiteName()`, `getSiteUrl()`, `getSiteSlogan()`, `getSiteLogos()`, `getLogoUrl($type)`,
  `getFooterText()`, `getTheme()`, `getSkin()`, `getDealershipName()`, `getDealershipDetails()`, `getTaxRates()`,
  `getGSTRate()`, `getTDSRate()`, `getSetting($key)`, `getAllWithMetadata()`.
- `SystemSettingExportImportService`: `exportJson()`, `exportCsv()`, `exportExcel()`, `importJson($json)`,
  `importCsv($csv)`, `importSettings($arr)` (→ counts / errors), `getTemplates()`, `static getTemplateData()`.
- Models: `SystemSetting` (scopes `editable()`, `visible()`, `byTopic($t)` — key prefix counts as topic,
  `byGroup($g)`; statics `get`, `getValue`, `set`, `has`, `ensure($key, $value, $type, $label, $description)`,
  `getByTopic`, `allByTopic`, `getAllAsArray`, `allForExport`, `flushCache($key)`, `flushAllCache()`),
  `SystemSettingAudit` (`setting()`, `user()`, `getOldValueDecoded()`, `getNewValueDecoded()`).

## Core legacy models (`App\Models\Core`)
| Model | Status |
|---|---|
| `ImportLog` (`import_logs`) / `ExportLog` (`export_logs`) | in use by importers / exports: `user()`, scopes `successful()`, `failed()`, `partial()` / `downloaded()`; `getImportTypeLabel()` / `getExportTypeLabel()`, `getStatusBadgeClass()`, `markAsDownloaded()`, `getFormattedFileSize()` |
| `Garage` (`garages`) | `person()`; legacy |
| `ApprovalHierarchy`, `GraphNode`, `GraphEdge` | **dead** (old approval graph, DEC-063 replaced it) — removal awaiting sign-off; do not use. Members for reference: `ApprovalHierarchy::approver()`, `graphNode()`, `initiate($data)`; `GraphNode::user()`, `outgoingEdges()`, `incomingEdges()`, `nodeable()`, `getSubtree($topic, $combo)`, `getSubtreeUserIds($topic, $combo)`; `GraphEdge::fromNode()`, `toNode()`, `matches($topic, $combo)` |

## Gotchas
- Never `Keyvalue::where(...)` in feature code (golden rule 4) — use `KeywordValueService`.
- `parent_id` is a comma list: `FIND_IN_SET` or `OrgService::keywordValueByParentCode()`, not `->parent()`.
- Resolve synonyms **before** matching codes in imports; `resolve()` never returns null for non-empty input.

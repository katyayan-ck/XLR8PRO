# Plan: vehicle specifications, features, galleries and compare (DEC-092, to-do W14)

> **Status (01-10-2026): ✅ done** — Phases 1–5. Open: load the owner's samples on UAT and fix the unmatched names the import report lists.

## Context
The owner asked (30-09) for: model-level images, a PDF brochure and category-wise specifications; variant-level
feature mapping and an image gallery; Excel import / export of both; and compare — intra-model (variants of one model,
by features) and inter-model (models of the same segment, by specifications). Samples (project root, git-ignored):
- `Vehicle_Specifications.xlsx` — one sheet per segment group (COMMERCIAL, LMM, LMM EV, PERSONAL, PERSONAL EV), rows
  Head → SubHead (Axle, Battery, Brakes, Engine, Fuel, Performance, Seating, Steering, Suspension, Transmission, Tyres,
  Dimensions, Warranty …), one column per model (by name), values text ("2523 CC", "DISC", "-NA-").
- `Vehicle-Features.xlsx` — one sheet per model, rows Feature group → Feature (Comfort & Convenience, Safety & Trust …),
  one column per variant (by name), values `Yes` / `---`.

Our data: a model has a code (`BOLERO-NEO`); a variant row is one **colour** (`code` + `color_code`), so one variant
code (the trim) has several rows (2 652 rows / 414 trims on the test copy). Sample names differ from ours
("BOLERO NEO N10 BS6.2 - R" vs "BOLERO NEO N10 BS6.2 - R - REFRESH").

## Owner decisions (30-09, DEC-092)
1. **Features** belong to the variant code (trim) — shared by all its colours.
2. **Gallery:** both levels; each uploaded image is bound by the user to the trim (all colours) or to one colour.
3. **Masters:** categories and items (specification / feature) are master lists; an import **adds** unknown items and
   reports them; items can be renamed / merged / re-ordered later.
4. **Compare:** an admin screen and an app API.

## Design
- **Tables** (migrations, guarded; code-based relations; six audit columns; entity services, DEC-050):
  - `xlr8_vehicle_spec_item` (code, category, name, unit, sort, is_active) — master of specification items.
  - `xlr8_vehicle_model_spec` (model_code, spec_item_code, value) — unique (model, item).
  - `xlr8_vehicle_feature_item` (code, feature_group, name, sort, is_active) — master of features.
  - `xlr8_vehicle_trim` (variant_code unique, model_code, notes) — one row per trim: owns the trim gallery.
  - `xlr8_vehicle_trim_feature` (variant_code, feature_item_code, value) — value `Yes`, `No` or a short text.
- **Media** (Spatie media library, `public` disk, 250 px preview + 100 px thumb): `VehicleModel` → `images` (many),
  `brochure` (single PDF); `VehicleTrim` → `gallery` (trim level); `Variant` (colour row) → `gallery` (colour level).
  A colour's gallery = its own images + the trim images.
- **Screens** (Vehicle module): model page tabs *Images & brochure* and *Specifications* (grouped, editable in place);
  variant page tabs *Features* (grouped switches / text) and *Gallery* (drop-zone with a level choice: this trim / a
  colour; current images with Remove). Masters: *Specification items* and *Feature items* (rename, merge, order,
  deactivate).
- **Excel** (codes, master-fed dropdowns like the Vehicle Info workbook):
  - specifications: one sheet per segment, rows category / item, columns = models (`CODE` + name header);
  - features: one sheet per model, rows group / feature, columns = variant codes (+ name);
  - import through the entity services; unknown items created and reported; blank cell = keep, `-` / `-NA-` = not
    applicable (stored as N/A).
  - **one-time loader** for the owner's sample format: matches model / variant columns by normalised name, reports
    what did not match (fixed on screen or in our workbook).
- **Compare** (`App\Services\Vehicle\CompareService`): `variants(modelCode, variantCodes[])` → feature matrix (groups →
  features × trims, differences flagged); `models(modelCodes[])` → specification matrix, **same segment enforced**
  (`VEHICLE_COMPARE_SEGMENT` error otherwise); "show only differences" option. Admin screen (pick model → trims, or
  segment → models; side-by-side, sticky first column, print); API `GET api/v1/vehicles/compare/variants`,
  `.../compare/models` + docs / Postman; permissions `VEH_CMPR_VIEW` (compare), `VEH_CONT_EDIT` (content), minted by
  migration and granted to superadmin (owner assigns further).

## Phases
1. **Data:** migrations, models, entity services, media collections, permissions; unit / feature tests.
2. **Screens:** model images / brochure / specifications; variant features / gallery (level choice); masters.
3. **Excel:** export / import of our format; the one-time loader for the samples (with a match report).
4. **Compare:** service, admin screen, API + docs; tests.
5. **Wrap-up:** guides (`tech-guides/modules/vehicle.md`), rules, smoke as superadmin and a scoped user.

## Verification
Feature tests per phase (entity rules, import adds items, blank keeps, level-bound gallery, compare same-segment rule,
API envelope); load the owner's samples on the test copy and review the match report; smoke of the new screens.

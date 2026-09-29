# Plan: pricing masters, auto recalculation, sync stamp and logo (DEC-083)

Decisions are in `docs/decisions/decision-log.md` DEC-083. Each phase is its own commit, with tests, the changelog,
guide updates and quality gates.

## Phase A — foundations
- **Sync stamp:**
  - Setting `pricing.last_updated_at` (config/platform.php) and a `PricingSyncStamp` service with `touch()`, written
    once per request / job.
  - Hooks:
    - `SnapshotPublisher` (publish / expire);
    - model saves of Segment / SubSegment / VehicleModel / Variant (observer);
    - Accessory / AccessoryScope.
  - Exposed in the existing `api.settings.pricing` endpoint.
- **Auto recalculation:**
  - `Engine\PricingRecalcService`:
    - `mark(scope, wef, reason)` resolves the affected vehicle codes (segment / model / variant / permit / fuel …, or
      all for broad rules);
    - stores them in a cache queue;
    - dispatches the debounced `RecalculateAffectedJob` (unique, delay 60 s).
  - The job reuses the snapshot build + publish of `PricingCalculationService` (refactored so the vehicle loop does
    not need a session). It skips held lists and publishes at max(WEF, today).
  - Each run is logged in the new table `xlr8_vehicle_pricing_recalc_runs`.
  - `PricingSessionService::gate()` blocks master edits while a process is open.
- **Master kit:**
  - `App\Support\PricingMaster\MasterDefinition` (key, label, permission, entity service / model, list columns, form
    fields, filters, export columns, import mapping, `recalcScope(row)`).
  - `Admin\Pricing\MasterController` routes: `pricing/masters/{master}` — index, rows (JSON), create / store,
    edit / update, destroy, export (.xlsx), import (queued; chunked, one transaction per chunk, progress).
  - Views: an AG Grid list with column filters + a form built from the definition's fields.

## Phase B — add-on and discount masters
- Dealer Charges, RSA, Shield, Exchange, Corporate: sheets the same as Addon-N-Discounts.
- Loyalty — end to end:
  - the `LOYALTY` discount type;
  - `ComponentResolver` options and contract `discounts.loyalty`;
  - getPricing `loyalty`;
  - the Addon-N-Discounts Loyalty sheet;
  - the quotation adapter (Loyalty Bonus);
  - the Price List column.
- Discounting Breakup: the price rows' scheme columns (NV / OV) per vehicle; edit / import / export; new rows go
  through `PriceService`.

## Phase C — rules and insurance masters
- RTO Rules: the existing screen is replaced by the kit, with import / export in the RTO workbook layout.
- Insurance Rules: base rules (+ IDV slots, add-on rates) with CRUD; import / export in the Insurance workbook layout.
- New masters, each with a migration and an entity service:
  - Insurance Companies;
  - Insurance Preferences (segment + permit + optional model → ordered companies; the engine's `companyOrder()`
    reads them, falling back to the old `InsDefault`);
  - Insurance Add-ons (code, name, default flag, order; the engine's default add-ons come from the flag).

## Phase D — Accessories
- A screen on `AccessoryService`: catalogue + scopes, typed-sheet import / export.
- Retire the `import:vehicle-accessories` one-sheet importer (BUG-179).
- No recalculation; bumps the sync stamp.

## Phase E — logo and menu
- Settings image type (upload to the public disk).
- Setting `branding.logo`, used in both menu layouts (linking to the dashboard), the login page and the PDFs / prints.
- The Admin → Pricing menu lists every master (permission-gated), plus the recalculation log.

## Verification
- Feature tests per phase:
  - CRUD permission, validation;
  - import / export round-trip;
  - the recalculation is queued and the affected vehicles are republished;
  - the sync stamp is bumped;
  - Loyalty end to end;
  - the logo is rendered.
- Full suite at the end; the smoke sweep before merge.

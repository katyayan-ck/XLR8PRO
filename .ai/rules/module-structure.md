---
description: Mandatory Module/Process/Activity structure for admin routes, namespaces, permissions, and menu visibility. Load for ANY work touching app/Http/Controllers/Admin/**, routes/backpack/**, app/Services/**, app/Models/**, or the admin menu.
paths:
  - app/Http/Controllers/Admin/**
  - app/Services/**
  - app/Models/**
  - routes/backpack/**
  - resources/views/vendor/backpack/ui/inc/menu_items.blade.php
---

# Module / Process / Activity Structure (Mandatory, Whole-App)

Recorded 20-09-2026 at explicit user request, following the RBAC permission rollout
(`docs/refactor/TASK_STATE.md`). **This is a permanent, project-wide standard — every admin-panel
entity must conform, old and new.** Unlike ordinary settled decisions, existing non-conforming
routes/permissions are being actively migrated to this standard (see `docs/refactor/TASK_STATE.md`
section 6+ for rollout progress), not just grandfathered in.

## 1. The hierarchy

```
Module   (e.g. Sales)
 └── Process   (e.g. Booking, Quotation, Enquiry, Campaign)
      └── Activity   (e.g. List, Create, Edit, Delete, and business-specific ones like
                       ReceivePayment, KycEdit, RtoEdit — one per distinct user-facing action)
```

Every admin-panel entity (CrudController or custom admin controller) belongs to exactly one Module
and one Process. Every distinct action it exposes is an Activity.

## 2. Module codes (established, do not invent new ones without updating this table)

These match the existing top-level folders under `app/Http/Controllers/Admin/`:

| Module folder | Code | Covers |
|---|---|---|
| `Sales` | `SLS` | Booking, Quotation, Enquiry, Campaign, Lead, LeadSource |
| `Accounts` | `ACC` | JournalVoucher, Receipt |
| `Finance` | `FIN` | Finance |
| `Insurance` | `INS` | Insurance |
| `Rto` | `RTO` | Rto |
| `Spares` | `SPR` | SpareRequest |
| `Vehicle` | `VEH` | Brand, Color, Model, Segment, SubSegment, Variant |
| `Org` | `ORG` | Branch, Department, Designation, Division, Employee, Location, Person, PersonAddress, PersonBankingDetail, PersonContact, User, Vertical |
| `Iam` | `IAM` | Modules, Permission, Process, Role |
| `Pricing` | `PRC` | Hold, Insurance, PricingReset, PricingWorkflow, RtoRule, TcsConfig (pre-existing, not part of this rollout) |
| `Utils` | `UTL` | KeyValue, KeywordMaster, SystemSetting |

A genuinely new module (not one of the above) needs a new row here before it's used — pick a short,
stable 3-4 letter uppercase code and add it to this table in the same change that introduces it.

## 3. Process codes (already shipped — do not rename these without a dedicated migration)

| Process | Code | Status |
|---|---|---|
| Enquiry | `ENQR` | Permissions shipped (`SLS_ENQR_*`) |
| Quotation | `QUOT` | Permissions shipped (`SLS_QUOT_*`) |
| Booking | `BKNG` | Permissions shipped (`SLS_BKNG_*`) |
| Branch | `BRCH` | Permissions shipped (`ORG_BRCH_*`) |
| Department | `DEPT` | Permissions shipped (`ORG_DEPT_*`) |
| Designation | `DESG` | Permissions shipped (`ORG_DESG_*`) |
| Division | `DIVN` | Permissions shipped (`ORG_DIVN_*`) — shared with Vertical |
| Employee | `EMPL` | Permissions shipped (`ORG_EMPL_*`) |
| Location | `LOCN` | Permissions shipped (`ORG_LOCN_*`) |
| Person | `PRSN` | Permissions shipped (`ORG_PRSN_*`) — shared with PersonAddress/PersonBankingDetail/PersonContact |

For every other process, derive a short (3-5 letter) uppercase abbreviation of the entity name
(e.g. Campaign → `CMPN`, Lead → `LEAD`, LeadSource → `LDSR`, Receipt → `RCPT`) the first time it's
migrated, and add it to this table in the same change — never invent a second code for the same
process later.

## 4. Permission naming: `{MODULE}_{PROCESS}_{ACTIVITY}`

Format: all-uppercase, underscore-separated, abbreviated codes (not full words) — e.g.
`SLS_BKNG_VIEW`, `SLS_BKNG_KYC`, `SLS_ENQR_CREATE`. This is the permanent standard, confirmed
explicitly by the user over the alternative lowercase-full-word style (`sales_booking_list`) despite
that being the phrasing used when the rule was requested — do not switch to the lowercase style.

- Core CRUD activities use `VIEW` / `CREATE` / `EDIT` / `DELETE`.
- Distinct business sub-processes get their own activity code rather than being folded into
  `EDIT`/`VIEW` (e.g. `SLS_BKNG_KYC`, `SLS_BKNG_RTO`, `SLS_BKNG_REFUND` — see
  `BookingCrudController` for the reference implementation of this granularity).
- **Always mint with `guard_name = 'web'`**, never `'backpack'` — despite `backpack_user()`
  authenticating via the `'backpack'` guard, Spatie's default guard resolution for a `User` model
  picks `'web'` (both guards share the same `App\Models\User` provider; `'web'` is registered first).
  Minting with `'backpack'` silently never matches — confirmed the hard way in batch 26.
- Gate with inline `if (! backpack_user()->can('CODE')) { abort(403, '...'); }` as the first
  statement of the action method — **not** `$this->middleware('permission:...')` in `setup()`.
  Confirmed that pattern does not work in this app: no `permission` middleware alias is registered
  in `bootstrap/app.php`, and even if it were, `setup()` runs inside `CrudController`'s own
  constructor-middleware closure, which executes *after* the router has already gathered the
  controller's middleware list for the request — middleware added from inside `setup()` is too late
  to take effect (same class of timing trap as BUG-038).
- Backpack's `DeleteOperation`/`ListOperation`/`UpdateOperation`/`CreateOperation` trait defaults
  (`destroy`, `search`, `showDetailsRow`, `edit`, etc.) grant their own operation access
  automatically and bypass any check added elsewhere in the class. If a route reaches one of these
  un-overridden trait methods, override it explicitly: alias the trait method (`use Trait { method
  as traitMethod; }`) and define your own method that checks permission then delegates — see
  `EnquiryCrudController`/`BookingCrudController` for the reference pattern. Always check whether
  `Route::crud()` is registered for a controller before assuming these are unreachable.
- **Critical, easy to miss: before converting a `Route::crud()` registration to explicit
  `Route::get()`/`post()`/etc. calls (required by section 6 below), check whether the controller's
  permission checks live inside `setupListOperation()`/`setupCreateOperation()`/
  `setupUpdateOperation()` hooks rather than as inline checks in an overridden `index()`/`create()`/
  `edit()`/`update()` method.** Those hooks are dispatched by Backpack based on
  `\Route::getCurrentRoute()->action['operation']` (see `vendor/backpack/crud/src/app/Library/
  CrudPanel/Traits/Operations.php::getCurrentOperation()`) — a key that `Route::crud()`'s own
  `setupXRoutes()` methods always set (`'operation' => 'list'|'create'|'update'|'delete'`), but that
  a plain `Route::get($uri, [Controller::class, 'method'])->name(...)` registration does **not**
  include. Converting away from `Route::crud()` without preserving this key silently breaks the
  hook — and any permission check that lives *only* inside it — with no error, no exception, just a
  fully unguarded route (confirmed live: `SystemSettingCrudController`/`SpareRequestCrudController`
  went from `403` to `200` on `index`/`create` after a routine URL migration, purely from dropping
  this key; see known-bugs-report.md BUG-064). If the controller has **both** the hook check *and*
  a redundant inline check in the overridden method (the common case for controllers migrated in
  batches 25-32 — e.g. `KeyValueCrudController`), the inline check alone is sufficient and no
  special handling is needed. If the hook is the *only* check (no method override at all — the
  `setupXOperation()` body is the sole customization point, common for controllers that were never
  touched by the earlier permission rollout), either (a) preserve the key by registering with the
  full options-array form — `Route::get($uri, ['uses' => Controller::class.'@method', 'as' =>
  'name', 'operation' => 'list'])` (note: `'uses'` must be the `Controller@method` **string** form
  here, not the `[Controller::class, 'method']` array-tuple — mixing them throws
  `ReflectionFunction::__construct(): ... array given`, confirmed the hard way) — or (b) convert the
  check to an inline override instead, matching every other controller. Before touching any new
  controller, `grep -n "setupListOperation\|setupCreateOperation\|setupUpdateOperation\|public function index\|public function create\|public function edit\|public function update"` it first
  to know which situation you're in.

## 5. Namespace pattern (PHP)

```
app/Http/Controllers/Admin/{Module}/{Process}/{Process}CrudController.php
    → namespace App\Http\Controllers\Admin\{Module}\{Process};
app/Services/{Module}/{Process}/{Process}Service.php
    → namespace App\Services\{Module}\{Process};
app/Models/{Module}/{Process}.php  (only when the model doesn't already have an established home —
    do not move existing models out of app/Models/CRM, app/Models/Module/Booking, etc. as part of
    a routine permission/route migration; that's a separate, larger decision)
app/Http/Requests/{Module}/{Process}/{Process}Request.php
```

Module is chosen by **business domain, not menu placement** — confirmed precedent: `Admin\Pricing\*`
sits outside the "Sales" menu dropdown despite being sales-adjacent, because pricing is its own
business domain, not a UI grouping.

## 6. Route pattern

- **URI**: kebab-case, nested by module/process: `/admin/{module-slug}/{process-slug}/{activity-slug}`
  — e.g. `/admin/sales/booking/list`, `/admin/sales/booking/{id}/kyc-edit`. Lowercase, exactly as
  `.ai/rules/conventions.md` already specifies — the capitalized example used when this rule was
  requested (`Sales/Bookings/List`) was for readability only, confirmed not literal.
- **Route name**: dot notation, lowercase, matching the module/process/activity hierarchy:
  `sales.booking.list`, `sales.booking.kyc-edit`.
- **This applies retroactively to already-shipped routes, not just new ones** — explicit user
  decision, chosen over the lower-risk "new work only" alternative. Every route rename must be
  paired with an audit of `resources/views/**/*.blade.php` for hardcoded old URLs (JS/AJAX calls,
  `<form action="...">`, anything not using the `route()` helper) in the same batch, since those
  don't update automatically when a route name/URI changes and fail silently (no build-time error).
  Prefer `route('name')` / `{{ route('name') }}` over hardcoded strings when touching a view for
  this reason — flag (don't silently rewrite unrelated) any hardcoded URL found outside the current
  batch's scope as a new known-bugs entry.
- **The audit must also cover the controller's own PHP source, not just Blade views** (see
  known-bugs-report.md BUG-066 — found in batch 35, after batches 30-34 had already shipped without
  this check). Controllers routinely build `backpack_url()` calls internally for AJAX grid
  action-button URLs (inside a mapped array assembling an `<a href="...">` string) and
  `redirect(backpack_url(...))` calls after `store()`/`update()`. `grep -rn "backpack_url(" <file>`
  the controller itself, same as any Blade view, before considering a batch's URL rename complete.
- **Also grep for the bare, multi-line-formatted form**: `grep -n "backpack_url($"` (opening paren,
  nothing after it on the line) — a call written as
  `backpack_url(\n    "old/path/{$var->id}/edit"\n)` does not match a single-line
  `backpack_url('old` / `backpack_url("old` search and will be silently missed. Every bare-paren
  hit found this way must be opened and its next 1-3 lines inspected by hand.
- Route files live under `routes/backpack/*.php`, loaded automatically by
  `AppServiceProvider::boot()`'s glob — no new loader needed for a new module.

## 7. Menu visibility must match permissions

Every `<a>`/menu entry in `resources/views/vendor/backpack/ui/inc/menu_items.blade.php` (985 lines,
currently **zero permission checks** — found during this rule's introduction) must be wrapped so it
only renders when `backpack_user()->can('THE_RELEVANT_PERMISSION')` is true. Use a `@can` /
`@if (backpack_user()->can(...))` guard per item (or per dropdown section, if every item inside
shares one permission) — do not rely on the route-level 403 alone, since an unauthorized user
seeing a link that 403s on click is a worse experience than not seeing it, and was not previously
enforced anywhere in this file.

## 8. Migration is happening in batches of 10-15 entities

Per explicit user instruction: full-app retrofit (routes, namespaces, permissions, menu gating) is
in progress across the whole admin panel, not just newly-touched controllers. Track progress in
`docs/refactor/TASK_STATE.md`; log every batch in `docs/refactor/ai-changelogs-DD-MM-YYYY.md` and
`docs/refactor/ai-findings-DD-MM-YYYY.md`; log every newly-found bug in
`docs/refactor/known-bugs-report.md` — same standing process as the permission rollout that preceded
this rule. Test every route rename and permission gate via the established HTTP-kernel,
rolled-back-transaction methodology (see any batch 25-29 changelog entry for the exact pattern) —
`php artisan test` cannot be relied on in this environment (BUG-053: `phpunit.xml` points at a
nonexistent database).

## Group Backpack routes into per-module route files
Routes must be split across multiple files under `routes/backpack/`, grouped by the same Module/Process structure as `.ai/rules/module-structure.md` (e.g. `booking.php` for Sales/Booking+Quotation, a dedicated file per module rather than dumping everything into `core.php`). When adding a new module/process, add or extend its own route file instead of appending to `core.php`. All files are auto-loaded via `AppServiceProvider::boot()`'s glob — no manual registration needed for a new file.

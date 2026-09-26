---
description: Backpack admin controllers, routes, permissions, menu and admin views. Load for any admin-panel work.
paths:
  - app/Http/Controllers/Admin/**
  - routes/backpack/**
  - resources/views/admin/**
  - resources/views/vendor/backpack/**
---

# Admin panel (Backpack 7, Track A)

## Module / Process / Activity
Every admin entity belongs to one Module and one Process; every user-facing action is an Activity.

| Module folder | Code | Processes |
|---|---|---|
| Sales | SLS | Enquiry `ENQR`, Quotation `QUOT`, Booking `BKNG`, Campaign `CMPN`, Lead `LEAD`, LeadSource `LDSR` |
| Accounts | ACC | JournalVoucher, Receipt `RCPT` |
| Finance / Insurance / Rto | FIN / INS / RTO | (imports only today) |
| Spares | SPR | SpareRequest |
| Vehicle | VEH | Brand, Color, Model, Segment, SubSegment, Variant |
| Org | ORG | Branch `BRCH`, Department `DEPT`, Designation `DESG`, Division `DIVN` (+Vertical), Employee `EMPL`, Location `LOCN`, Person `PRSN` (+Address/Banking/Contact), User |
| Iam | IAM | Modules, Permission, Process (Role screen retired → Designation, DEC-018) |
| Pricing | PRC | Hold, Insurance, PricingReset, PricingWorkflow, RtoRule, TcsConfig |
| Utils | UTL | KeyValue, KeywordMaster, SystemSetting |

New module/process → add its code here in the same change; codes never change once shipped.
Module is chosen by business domain, not menu placement.

## Permissions
- Format `{MOD}_{PROC}_{ACT}`, uppercase; CRUD activities `VIEW/CREATE/EDIT/DELETE`; distinct business
  steps get their own activity (`SLS_BKNG_KYC`, `SLS_BKNG_RTO`, `SLS_BKNG_REFUND`).
- Mint with `guard_name = 'web'` (never `'backpack'` — it silently never matches).
- Gate inline as the **first statement** of every action: `if (! backpack_user()->can('CODE')) abort(403, '…');`.
  Do **not** use `$this->middleware(...)` in `setup()` or constructors — it runs too late / doesn't exist
  on Laravel 11+ controllers (BUG-159).
- Backpack operation trait methods (`destroy`, `search`, `showDetailsRow`, `edit`…) bypass your checks.
  Override via alias: `use DeleteOperation { destroy as traitDestroy; }` then check → `return $this->traitDestroy($id);`.
  (`parent::deleteCrud()/storeCrud()/updateCrud()` do not exist in Backpack 7.)
- **Hook-timing trap:** `setupListOperation()`/`setupCreateOperation()`/`setupUpdateOperation()` only run when
  the route carries an `'operation'` key (`Route::crud()` sets it). If you register routes explicitly and the
  permission check lives only in a hook, use `Route::get($uri, ['uses' => 'Controller@method', 'as' => 'name',
  'operation' => 'list'])` (string `uses`, not the array tuple) — or move the check inline (preferred).
  Before touching a controller: `grep -n "setupListOperation\|setupCreateOperation\|setupUpdateOperation\|function index\|function create\|function edit\|function update"`.

## Routes
- Files under `routes/backpack/*.php`, one per module group, auto-loaded by `AppServiceProvider::boot()`.
- URI `/admin/{module}/{process}/{activity}` kebab-case; name `module.process.activity`.
- When renaming a route/URI, audit Blade **and** controller PHP for hardcoded URLs:
  `grep -rn "backpack_url(" <files>` and the multi-line form `grep -n "backpack_url($"`. Prefer `route('name')`.
- Every route must point at an existing method (dead routes were purged 26-09-2026; keep it that way).

## Menu
`resources/views/vendor/backpack/ui/inc/menu_items.blade.php` — every entry wrapped in a `can()` check for
its permission. Hidden-until-Track-B items are left as Blade comments with the DEC id.

## Controllers & views
- Most CrudControllers override `index/create/store/edit/update` and render custom Blade via
  `setListView/setCreateView/setEditView`; lists use AG-Grid. Show operation is not used.
  When using the field/column DSL use fluent syntax (`CRUD::field('x')->type(...)`).
- Views mirror controller folders: `Admin/Sales/Booking/*` → `resources/views/admin/sales/booking/*`.
- Validation names/labels via `__('module.fields.x')` (`resources/lang/en/*`), never re-typed.
- Only customised Backpack view overrides live in `resources/views/vendor/backpack` (identical copies were
  removed 26-09-2026 — don't re-publish whole view folders).
- Git history is the backup for replaced/removed views (no `resources/views/backup/` copies anymore).

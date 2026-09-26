---
description: Schema, models, migrations and query safety. Load for model, migration, seeder or query work.
paths:
  - app/Models/**
  - database/**
---

# Database & models

## Schema facts (live `xlrm`, MySQL 8.4)
- ~135 tables. Prefixes: `xlr8_admin_*` (org/person/employee), `xlr8_iam_*` (permissions, OTP, devices),
  `xlr8_crm_*` (enquiries 60k, follow-ups 91k, quotations), `xlr8_booking_*` (legacy-port booking tables),
  `xlr8_vehicle_*` / `xlr8_vehicle_pricing_*`, `xlr8_spare_*`, `xlr8_utils_*` (settings, keyvalue, comm, noty, docs),
  `xlr8_system_*` (jobs, sessions). `xlr8_approval_*` is reserved.
- Spatie **roles table = `xlr8_admin_designation`** (config/permission.php).
- Schema lives mostly outside migrations (only a handful exist); normalisation is manifest-driven (DEC-003).
  Look up live columns with Boost `database-schema` or `.ai/knowledge/db/<prefix>.md` — never assume.
- Known warts being normalised: varchar money, magic int statuses (booking `status` '1'–'8'), typos
  (`chasis_no`, `recieving_status`), 4 collations, missing indexes on `*_code`, `bmpl_pincodes` without PK.

## New tables
- Lowercase snake_case, module prefix, six audit columns (`created_at/by`, `updated_at/by`, `deleted_at/by`,
  `*_by` BIGINT UNSIGNED); append-only log tables may omit update/delete audit.
- Soft deletes on domain tables; `DECIMAL(15,2)` money; business keys `*_code` with unique index; index names ≤ 64 chars.
- Code-based relations (`belongsTo(Segment::class, 'segment_code', 'code')`), no SQL FK constraints on module tables.
- Files go through Spatie Media Library — never ad-hoc file columns.

## Migrations
- Guard every change (`Schema::hasTable/hasColumn`), always a working `down()`; never `dropIfExists` a live table.
- Run on local only; then `php artisan testing:refresh-db --force`. Other environments via deploy.

## Models
- Extend `App\Models\BaseModel` (soft delete with actor, `created_by/updated_by` stamping from the backpack
  guard, media collections, generic scopes `active/inactive/dateRange/createdBy/newest/oldest`). Pivots/logs may extend Model.
- Explicit `$fillable` on new models (the legacy `$guarded=['id']` pattern in `Module/*` is not to be copied).
- Casts: override `casts()` merging `parent::casts()`; accessors in legacy `getXAttribute()` style.
- No per-model observers/actor stamping (BaseModel does it). Scope signatures must match BaseModel
  (`scopeActive(Builder $query): Builder`) or the class fatals on load.
- Media: `->useDisk('public')` explicitly; `{entity}_image` singleFile, `{noun}-proof` for proofs;
  `getFirstMediaUrl('collection')`; conversions 250px preview + 100px thumb, queued.
- Namespace must match path exactly **including case** (Linux autoload is case-sensitive).

## Queries
- Eager load; select needed columns; `chunkById()` on large tables; never `Model::all()` on large tables.
- Lookups through cached services (`OrgService`, `KeywordValueService`), not direct table reads.
- Multi-table writes in `DB::transaction()`. Sequences/numbering use `lockForUpdate()`.

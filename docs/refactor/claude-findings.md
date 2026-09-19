# Environment & Tooling Check — Findings

Read-only verification pass. No application files were changed as part of this check (one bug
was discovered along the way — see §4 — but was not touched).

---

## 1. Rules loaded — confirmation

Read `CLAUDE.md` (Laravel Boost guidelines, checked into the repo) and `.ai/rules/index.md`
(project-specific rule index). Contents now loaded and in effect for this session:

**From `CLAUDE.md` (Boost guidelines):**
- Laravel 12 / PHP 8.3 conventions, use `search-docs` before relying on version-specific APIs.
- Must open `.ai/rules/index.md` and read every matching rule file before creating/editing files
  in a covered path, plus `grep -rin` for keywords `.ai/rules` may not glob-match.
- `record-rule` only on explicit user request — never on my own initiative.
- Prefer Boost MCP tools (`database-query`, `database-schema`, `get-absolute-url`,
  `browser-logs`) over raw shell/file equivalents.
- PHP: curly braces always, constructor promotion, explicit types, PHPDoc over inline comments.
- Pint: run `vendor/bin/pint --dirty --format agent` after any PHP edit (not run this session —
  no files were edited).
- PHPUnit (not Pest) is the test framework; `testing-best-practices` skill governs test design.
- Backpack 7 paid-feature gate: check `composer show`/vendor before using any `backpack/pro`
  feature.
- Media Library: activate `medialibrary-development` skill for media work.
- Laradocs (`petebishwhip/laradocs`): `php artisan make:doc` for new docs pages, front-matter
  `title` required, no closures in `config/laradocs.php`.

**From `.ai/rules/index.md`:**
- Stack: Laravel 12 · Backpack 7 · PHP 8.3 · MySQL/MariaDB · Spatie Permission · Sanctum.
- Source-of-truth order: locked specs → `.ai/rules` → existing service code → informal chat.
- 8 area rule files exist: `architecture.md`, `services.md`, `database.md`,
  `vehicle-pricing.md`, `rbac-scopes.md`, `person-user.md`, `conventions.md`,
  `known-pitfalls.md` — each loads based on path globs in its front-matter.
- 16 "golden rules" always active: services are SSOT, thin controllers, no SQL FK constraints
  on new tables, six audit columns + soft delete on every business table, no `Model::all()` on
  large tables, lookups only through `KeywordValueService`, `OrgService`/`VehicleService` cache
  TTL = 3600s, `manage_pricing` permission gate, data-scoping bypass checks, full files (not
  diffs) unless a patch is explicitly requested.
- Two databases: `xlrm` (primary, canonical pricing SSOT) and `xlrk` (legacy CRM/booking,
  read-only reference — never treat as SSOT).
- Permission format: `MODULE_PROCESS_ACTIVITY`, uppercase, stable once published.

**Cross-check against last session's sweep** (`claude-first-inspection.md`, same repo, still
unresolved): several of these documented rules (FormRequest-only validation, the
`{success,message,data}` response envelope, `Admin/{Module}/{Process}/` CrudController nesting,
`SheetHeaderService`/`SynonymService` in every importer, `DocService` as sole media consumer,
`permission:` middleware enforcement) do not match what the code actually does. That file has
the full evidence; not repeated here. It's still sitting unactioned in the repo root — you asked
last time for the doc-vs-reality question to be written up for your review rather than decided
automatically.

---

## 2. Boost MCP tools — reachability

| Tool | Status | Result |
|---|---|---|
| `mcp__laravel-boost__application-info` | ✅ reachable | Returned full package/version list (see §3) |
| `mcp__laravel-boost__database-connections` | ✅ reachable | `default_connection: mysql`; configured connections: `sqlite, mysql, mariadb, pgsql, sqlsrv` |
| `mcp__laravel-boost__database-query` | ✅ reachable | Ran 3 read-only queries successfully (see §5) |

All three tools queried in this check responded correctly. No connectivity or auth issues with
the Boost MCP server.

---

## 3. Laravel version & key packages (from `application-info`)

- **PHP:** 8.3
- **Laravel framework:** 12.69.2
- **Database engine:** mysql

Key direct-dependency packages and installed versions (full list has 260+ entries including
transitive JS/PHP deps — trimmed to what's relevant to this app's conventions):

| Package | Version |
|---|---|
| backpack/crud | 7.1.20 |
| backpack/theme-tabler | 2.1.0 |
| backpack/basset | 2.0.14 |
| backpack/generators | 4.1.1 |
| laravel/sanctum | 4.3.3 |
| laravel/boost | 2.9.1 |
| laravel/mcp | 1.0.0 |
| laravel/pint | 1.32.1 |
| spatie/laravel-medialibrary | 11.23.8 |
| spatie/laravel-permission | 6.25.0 |
| spatie/laravel-translatable | 6.14.1 |
| owen-it/laravel-auditing | 14.0.6 |
| maatwebsite/excel | 3.1.70 |
| phpoffice/phpspreadsheet | 1.30.7 |
| barryvdh/laravel-dompdf | 3.1.2 |
| darkaonline/l5-swagger | 9.0.1 |
| kalnoy/nestedset | 6.0.6 |
| kreait/firebase-php | 7.24.1 |
| google/apiclient, google/cloud-vision | 2.19.4, 2.3.2 |
| revolution/laravel-google-sheets | 7.2.0 |
| markwalet/laravel-changelog | 1.13.0 |
| petebishwhip/laradocs | 1.1.1 |
| phpunit/phpunit | 11.5.56 |
| mockery/mockery | 1.6.15 |
| fakerphp/faker | 1.24.1 |
| tailwindcss (npm) | 4.1.17 |
| vite (npm) | 7.2.6 |

No `backpack/pro` or other paid Backpack add-on found in the package list — confirms the
Backpack paid-feature gate in `CLAUDE.md` is relevant (no PRO fields/filters/operations
available; only FREE Backpack CRUD).

---

## 4. Route listing — BLOCKED by a fatal PHP syntax error

`php artisan route:list` fails outright:

```
ParseError: syntax error, unexpected token "/"
at app\Http\Controllers\UserImportExportController.php:174
```

**Root cause confirmed** (`php -l` on the file reproduces the same error, and the line was read
directly):

```php
// app/Http/Controllers/UserImportExportController.php:174
        }

        /                                                    // ← stray single slash
        $sheet->setCellValue('A' . 3, 'INSTRUCTIONS:');
```

Line 174 is a single `/` on its own line, almost certainly a typo for a comment (`//` or
`/**`) that was left incomplete. This is a **hard parse error**, not a warning — PHP cannot
compile this file at all, which means:
- Any command that needs to autoload/reflect this class fails, including `route:list`.
- Any real HTTP request that resolves to a route registered on `UserImportExportController`
  will fatal with a 500 at runtime, not just in tooling.
- Since this class is autoloaded by class name only when referenced, most of the app still
  boots fine (confirmed above — DB queries, `application-info`, etc. all worked normally) — but
  this controller is completely dead/broken right now.

**Because you asked for a report-only pass, I have not touched this file.** This is a one-line,
low-risk fix (delete the stray line or complete the comment) whenever you want it applied —
flagging here rather than fixing unprompted.

**Route inventory (worked around via a grep of `routes/*.php` and `routes/backpack/*.php`
instead, since the Artisan command is unusable):**

| Route file | Purpose |
|---|---|
| `routes/web.php` | Web loader |
| `routes/api.php` | API v1 |
| `routes/backpack/core.php` | Backpack admin core |
| `routes/backpack/booking.php` | Backpack booking module |
| `routes/backpack/pricing.php`, `routes/backpack/pricing_routes.php` | Backpack pricing module |
| `routes/console.php` | Artisan console routes |

Route-verb counts across all route files (approximate, regex-based since `route:list` doesn't
run):

| HTTP verb | Count |
|---|---|
| GET | 250 |
| POST | 65 |
| PUT | 25 |
| DELETE | 7 |

(347 total `Route::` verb calls found; the true route count after resource/group expansion would
normally come from `route:list`, which is unavailable until the syntax error is fixed.)

---

## 5. Database connection check

Ran three read-only queries via `mcp__laravel-boost__database-query` against the default
`mysql` connection:

1. `SELECT DATABASE() AS current_db, VERSION() AS db_version`
   → **`xlrm`**, MySQL/MariaDB server version **8.4.3**. Matches `.ai/rules/index.md`'s
   documented primary database name.

2. `SELECT TABLE_SCHEMA, COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA IN
   ('xlrm','xlrk') GROUP BY TABLE_SCHEMA`
   → **`xlrm`: 134 tables.** No row returned for `xlrk` — either that schema isn't present on
   this server/connection, or it's inaccessible to the credentials this connection uses. Given
   `.ai/rules/database.md` describes `xlrk` as a *separate* legacy database (not just a
   differently-prefixed table set within `xlrm`), this is worth confirming: is `xlrk` reachable
   from this environment at all, or was it intentionally excluded from local/dev?

3. `SHOW TABLES LIKE 'xlr8_%'` → 116 matching tables, spanning all documented prefixes
   (`xlr8_admin_*`, `xlr8_booking_*`, `xlr8_crm_*`, `xlr8_iam_*`, `xlr8_spare_*`, `xlr8_system_*`,
   `xlr8_user_*`, `xlr8_utils_*`, `xlr8_vehicle_*`, `xlr8_vehicle_pricing_*`, `xlr8_finexch_*`,
   `xlr8_financer_*`, `xlr8_cre_*`). Confirms the table-naming convention documented in
   `.ai/rules/database.md` §2 is real and consistently applied, and that the DB connection is
   fully live and query-capable, not just reachable.

**Conclusion: database connection is healthy.** No credential, connectivity, or schema-access
issues found for the `xlrm` connection. The one open question is whether `xlrk` should be
reachable from here and isn't (see above).

---

## 6. Summary & suggested next actions

| # | Finding | Severity | Suggested action |
|---|---|---|---|
| 1 | `UserImportExportController.php:174` has a stray `/` causing a hard `ParseError` | 🔴 High — breaks `route:list`/any tooling that reflects this class, and will 500 any real request routed to it | One-line fix (remove the stray line or complete the comment). Ready to apply on your go-ahead. |
| 2 | `xlrk` legacy database schema not visible from the current DB connection | 🟡 Worth confirming | Check whether `xlrk` should be configured/reachable in this environment, or whether it's intentionally excluded here. |
| 3 | Rules loaded confirm no `backpack/pro` installed | ℹ️ Informational | Any future request for a PRO Backpack feature (select2, filters, repeatable fields, etc.) needs the paid-package workflow from `CLAUDE.md`, not direct use. |
| 4 | `claude-first-inspection.md` (previous session's convention sweep) is still sitting in the repo root, unactioned | ℹ️ Informational | Carried over from last time — the doc-vs-reality conflicts and recordable pattern candidates there are still awaiting your decision. |

No `record-rule` calls were made and no application files were modified during this check.

<laravel-boost-guidelines>
=== .ai/00-project rules ===

# Xceler8 (XLRM) — project context

**What:** BMPL's dealership management system: enquiries → quotations → bookings (KYC, DMS, finance, insurance, RTO,
exchange, delivery, refunds, OTF) → accounts, plus vehicle master & pricing, org/HR/IAM, spares, platform utilities.

**Track A — this repo:** live app for UAT. Laravel 12, PHP 8.4, Backpack 7 (Tabler, no PRO), MySQL 8.4, Spatie
permission/medialibrary, Sanctum API (mobile app). **Do not add Filament here.** Track B (`D:\laragon\www\xceler8`,
Laravel 13 + Filament 5) is the rebuild; this app's data migrates to it at switch-over (DEC-001).

**Golden rules**
1. Read the real file before changing it; grep before writing new logic (SSOT services: `.ai/rules/services.md`).
2. Controllers thin (validate → one service → respond); services own logic; models own data access.
3. Business keys are codes (`person_code`, `branch_code`, `segment_code`…), not integer FKs.
4. Lookups via `KeywordValueService` / `OrgService` (cached), never direct KeyValue/org queries.
5. Never guess a business rule or override a locked spec — stop and ask (`tech-guides/frs-and-workflows/`).
6. Log every decision in `docs/decisions/decision-log.md` (DEC-NNN) **before** the change.
7. Tests run on `xlrm_testing`, never on `xlrm`.

**Load context on demand, not up front:** `tech-guides/README.md` maps each task to the few files to read (project card,
module cards → module guide → FRS section, workflow cards). Live state: `.ai/state/handoff.md`. Open bugs:
`.ai/state/bugs-index.md`. Rules in `.ai/rules/` load automatically by path; skills load by description. Never read
`_backup/` (superseded) and never load `docs/changelog.md` whole (grep it by date or id).

=== .ai/10-workflow rules ===

# Workflow (all AI tools)

**Git:** never work on `main` (working branch `dev/admin`, synced with the team branch `stage`). Commit at checkpoints as
`type(scope): message` (feat, fix, refactor, docs, test, chore, security). **Never push, force-push or rewrite history
without explicit approval in that turn.**

**Stop and ask (never decide alone):** history rewrite/push · any non-local DB operation · destructive changes to real
data (drops with rows, irreversible type changes, mass remaps) · deleting tracked files outside an approved list ·
environment/machine changes · new or major-upgraded dependencies · business-rule ambiguity or locked-spec conflict ·
auth/permission/secret changes · UAT-visible behaviour changes beyond an obvious bug fix.

**Records — update in the same commit as the change (user standing instruction):**
- Decision → `docs/decisions/decision-log.md` (DEC-NNN, append-only, before the change).
- Change → append to `docs/changelog.md` under today's `## YYYY-MM-DD` (files, before → after, reason, DEC/BUG ids).
- To-do → `docs/todo.md` Part 1: move the item's status. Task **completed** → append an accomplishment to Part 2 under
  today's date: what and why (to-do/DEC/BUG ids), files/routes/settings/migrations, how verified, what is left.
- Bugs → check `.ai/state/bugs-index.md` first; a new bug goes to `docs/bugs/open.md` immediately (next BUG-NNN, index
  row + entry); a fixed bug gets its Fixed line and moves, row and full entry, to `docs/bugs/closed.md`. Never delete an
  entry. Then `php artisan ai:refresh-context`.
- Plans → a new approved plan is saved in `tech-guides/frs-and-workflows/plans/` (and its index); when work on a
  plan moves, update its status header and the index row.
- Guides → the matching `tech-guides/` file for any change to a model, service, business rule, screen standard or API.
- Handoff → rewrite `.ai/state/handoff.md`: just done, in progress (exact next step, files, uncommitted work), open
  questions for the owner, how to verify. A new session must be able to continue from it alone.
- **Date-wise copies (user standing instruction):** keep `docs/daily/DD-MM-YYYY/` (today) in step with the cumulative
  files in the same commit — `handoff.md` = `.ai/state/handoff.md`, `changelog.md` = today's changelog entries,
  `accomplishments.md` = today's accomplishments. First commit of a day: create the folder (`docs/daily/README.md`).
- Code is commented (PHPDoc on every class and public method; inline comments only for non-obvious logic, with the
  DEC/BUG id) and formatted with pint before every commit.

**Continuity — resume from exactly where work stopped (user standing instruction):** a crash, a new session or a
different model must be able to continue from the files alone, never from memory. So:
- **On every task completion and every commit** update, in that same commit: the bug files (new / fixed / moved), today's
  accomplishments, the to-do row, the changelog and the handoff (cumulative + `docs/daily/DD-MM-YYYY/`). No commit leaves
  them stale; a task is not "done" until they say so.
- **Before starting a task** mark its to-do row 🟡 in progress and add it to the handoff's *In progress*.
- **During long tasks** (more than one step, or anything running in the background) keep the handoff's *In progress*
  current at each checkpoint: the exact next step, files touched, uncommitted changes, commands / jobs running, what was
  decided and why. Commit or note work in progress before any risky or long operation.
- **To resume:** read `.ai/state/handoff.md` → the named to-do rows → `git status` / `git log -5` → continue at the
  recorded next step; verify before redoing anything.

**Quality gates (every change)**
1. `php -l` on touched files; `vendor/bin/pint --dirty --format agent`.
2. Scoped `vendor/bin/phpstan analyse <files> --memory-limit=2G`; before merges the full `composer analyse` must say
   "No errors" (`phpstan-baseline.neon` holds the legacy errors, W4 — never regenerate it to hide new ones).
3. The narrowest tests: `php artisan test --compact --filter=…` (runs on `xlrm_testing`); known failures are in the
   handoff — don't add new ones. Full suite periodically; `php artisan test --group=smoke` only before merges.
4. HTTP smoke of only the touched screens as superadmin **and** a scoped non-superadmin user.

**Database:** schema changes are Laravel migrations (guarded with `Schema::hasColumn/hasTable`, working `down()`), run
locally only; never `dropIfExists` a live table; other environments get migrations via deploy. After local schema
changes migrate the test copy too (`DB_DATABASE=xlrm_testing php artisan migrate`).

**Output:** full files when creating; precise edits when changing; no placeholder code. No new documentation files
unless asked or required by this workflow.

=== .ai/20-architecture rules ===

# Architecture essentials

- **Layers:** Controller (FormRequest validation, permission check, call one service, shape response) →
  Service (`App\Services\{Module}\{Process}\*Service`, business logic, transactions, cross-model work) →
  Model (extends `App\Models\BaseModel`: soft deletes, audit actor stamping, media, generic scopes).
- **Hierarchy:** Module → Process → Activity. Permission = `{MOD}_{PROC}_{ACT}` (e.g. `SLS_BKNG_KYC`),
  minted with `guard_name = 'web'`. Route name `module.process.activity`, URI kebab-case under
  `/admin/{module}/{process}/…`. Codes and the module table: `.ai/rules/admin-backpack.md`.
- **Authorization:** inline `if (! backpack_user()->can('CODE')) abort(403);` as the first statement of each
  admin action (see the hook-timing trap in `.ai/rules/admin-backpack.md`). API: `auth:sanctum` +
  `validate_device`; Spatie `role`/`permission` middleware aliases are registered. SuperAdmin bypass is a
  Gate `before` hook in `AppServiceProvider`. Roles **are** designations (`xlr8_admin_designation`).
- **Data scoping:** `App\Services\IAM\DataScopeService` on `xlr8_admin_user_scopes`; `ScopedQuery`/`ScopedCrud`
  exist but are not yet switched on (decision pending). Jobs must not depend on a user scope.
- **API envelope:** `{http_status, success, code, message, data}` via `BaseController` helpers.
- **Dates:** app timezone `Asia/Kolkata`: timestamps are stored and compared in IST (DEC-046; rows before 26-09-2026 are UTC and are intentionally not converted). Displayed with `site_date()` / `@sitedate` (site setting `display.date_format`).
- **Labels:** `resources/lang/en/{module}.php` is the single source for field labels & validation names.
- **Money:** new columns `DECIMAL(15,2)`; legacy varchar money is being normalised (DEC-003).
- **Every job** sets `$timeout`, `$tries`, and implements `failed()`.
- **Entity writes (DEC-050, mandatory):** every create/edit of an entity (CRUD, import, API, job, seeder) goes through that entity's service (`App\Support\Entity\EntityService` subclass). The service's `fields()` is the **single** definition of each field's format, transformation, validation, label and immutability. Never write entity tables with `DB::table()->insert/update` or `Model::create()` outside the service, never re-declare rules in FormRequests/importers, and never correct data ad hoc: fix the field rule instead.
- **No `DB::` queries (DEC-093, mandatory):** all database reads / writes through Eloquent models (entity writes via the entity service); only `DB::transaction()` and begin / commit / rollBack are allowed; migrations exempt. `tests/Unit/Architecture/NoDbFacadeQueriesTest` blocks new uses.

=== .ai/30-laravel-tools rules ===

# Laravel 12 + Boost tools (compact; replaces Boost's generic sections — DEC-086)

- **Laravel 12 structure:** middleware, exceptions and routing are configured in `bootstrap/app.php`; providers in
  `bootstrap/providers.php`; console config in `routes/console.php`; commands in `app/Console/Commands` auto-register.
  No `app/Http/Kernel.php` / `app/Console/Kernel.php`.
- **Conventions:** follow the sibling files (structure, naming, idiom); descriptive names; reuse existing components; no
  new top-level folders or dependencies without approval. Create files with `php artisan make:* --no-interaction`.
  Casts via a `casts()` method where the model family already does. When modifying a column in a migration, restate
  all its attributes. Prefer named routes and `route()`.
- **Boost MCP tools** (prefer them over shell equivalents): `database-query` (read-only SQL), `database-schema` (before
  migrations / models), `search-docs` (before relying on version-specific framework / package APIs — scope with
  `packages`, use several short topic queries), `get-absolute-url` (before sharing a URL), `browser-logs`,
  `last-error`, `read-log-entries`.
- **Artisan / tinker:** `php artisan route:list --path=… --name=…`, `php artisan config:show key`;
  `php artisan tinker --execute '…'` with single quotes outside, double inside; don't create models in tinker without
  approval — prefer tests with factories.
- **Rules:** `.ai/rules/index.md` maps paths to rule files; they also load automatically by path. Record a rule with
  `record-rule` only when the user explicitly asks for one.
- If a front-end change doesn't show, the user may need `npm run build` / `npm run dev`.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

=== backpack/crud/backpack-crud rules ===

# Backpack (admin panel, Track A)

Backpack 7 + Tabler, no PRO. For any CrudController/field/column/operation work, **activate the `backpack-crud` skill** (full reference, loaded on demand) and follow `.ai/rules/admin-backpack.md` (permissions, hook-timing trap, routes, menu). Don't use PRO-only fields/filters (`select2`, PRO filters); `storeCrud/updateCrud/deleteCrud` don't exist in v7.

=== spatie/laravel-medialibrary/core rules ===

## Media Library

- `spatie/laravel-medialibrary` associates files with Eloquent models, with support for collections, conversions, and responsive images.
- Always activate the `medialibrary-development` skill when working with media uploads, conversions, collections, responsive images, or any code that uses the `HasMedia` interface or `InteractsWithMedia` trait.

=== petebishwhip/laradocs/core rules ===

# Laradocs

Only for dev documentation pages — activate the `laradocs-development` skill when writing docs pages.

</laravel-boost-guidelines>

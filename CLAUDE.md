<laravel-boost-guidelines>
=== .ai/00-project rules ===

# Xceler8 (XLRM) — project context

**What:** BMPL's dealership management system (DMS): enquiries → quotations (with approvals) → bookings
(KYC, DMS, finance, insurance, RTO, exchange, delivery, refunds, OTF) → accounts, plus vehicle master &
pricing, org/HR/IAM, spares, and shared platform utilities.

**Two tracks (see `docs/decisions/decision-log.md` DEC-001):**
- **Track A — this repo (`xlrm`)**: live app, stabilised for UAT. Laravel 12, PHP 8.4, Backpack 7
  (Tabler, no PRO), MySQL 8.4, Spatie permission/medialibrary, Sanctum API (mobile app consumer).
  **Do not add Filament here.**
- **Track B — `D:\laragon\www\xceler8`**: greenfield rebuild (Laravel 13, Filament 5, modular monolith)
  with an ETL toolkit so this app's data migrates at switch-over.

**Golden rules**
1. Read the real file before changing it; match existing contracts. Grep before writing new logic —
   the SSOT services are listed in `.ai/rules/services.md`.
2. Models own data access, Services own business logic, Controllers stay thin (validate → service → respond).
3. Business keys are code-based (`person_code`, `branch_code`, `segment_code`…), not integer FKs.
4. Never query KeyValue/org tables directly — use `KeywordValueService` / `OrgService` (cached).
5. Never guess a business rule or override a locked spec — stop and ask. Specs: `.ai/knowledge/specs/index.md`.
6. Every decision is logged in `docs/decisions/decision-log.md` (DEC-NNN) **before** the change.
7. Tests never touch `xlrm` — they run on `xlrm_testing` (`php artisan testing:refresh-db`).

**Where context lives** (load only what the task needs — see `.ai/README.md`):
`.ai/rules/` (auto-loaded by path) · `.ai/skills/` (on demand) · `.ai/knowledge/` (read when linked,
incl. generated DB schema cards in `knowledge/db/`) · `.ai/state/current.md` (active work) ·
`.ai/state/bugs-index.md` (open bugs; full tracker `docs/refactor/known-bugs-report.md` is grep-only).

=== .ai/10-workflow rules ===

# Workflow (all AI tools)

**Git**
- Never work on `main`. Branches: `feature/*` or `refactor/*` (current integration branch: `feature/integrations`).
- Commit at checkpoints with `type(scope): message` (feat, fix, refactor, docs, test, chore, security).
  **Never push, force-push or rewrite history without explicit approval in that turn.**

**Stop and ask (never decide alone)** — the user approves high-risk items explicitly:
history rewrite/push · any non-local DB operation · destructive changes to real data (drop tables/columns
with rows, irreversible type changes, mass remaps) · deleting tracked files outside an approved list ·
environment/machine changes · new or major-upgraded dependencies · business-rule ambiguity or locked-spec
conflict · auth/permission/secret changes · UAT-visible behaviour changes beyond an obvious bug fix.

**Logging (mandatory)**
- Decision → `docs/decisions/decision-log.md` (DEC-NNN, append-only, written before the change).
- Change → `docs/refactor/ai-changelogs-DD-MM-YYYY.md` (files, before → after, reason, DEC id).
- New bug found anywhere → `docs/refactor/known-bugs-report.md` immediately (BUG-NNN; never delete
  entries; update status in place; keep the index table current). Check `.ai/state/bugs-index.md` first.

**Quality gates (every change)**
1. `php -l` on touched files; `vendor/bin/pint --dirty --format agent`.
2. Scoped `vendor/bin/phpstan analyse <files> --memory-limit=2G`.
3. `php artisan test --compact` (or the narrowest relevant `--filter`) — runs on `xlrm_testing`.
   Known pre-existing failures are listed in `.ai/state/current.md`; don't add new ones.
4. HTTP smoke of touched screens as superadmin **and** a scoped non-superadmin user.

**Database**
- Schema changes are **Laravel migrations** (guarded with `Schema::hasColumn/hasTable`, working `down()`),
  run on local only. Never `dropIfExists` a live table. Other environments get migrations via deploy.
- Refresh the test copy after local schema/data changes: `php artisan testing:refresh-db --force`.

**Output**
- Full files when creating; precise edits when changing. No placeholder "rest unchanged" code.
- Don't create documentation files unless asked or required by this workflow.

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
- **Dates:** stored UTC; displayed with `site_date()` / `@sitedate` (site setting `display.date_format`).
- **Labels:** `resources/lang/en/{module}.php` is the single source for field labels & validation names.
- **Money:** new columns `DECIMAL(15,2)`; legacy varchar money is being normalised (DEC-003).
- **Every job** sets `$timeout`, `$tries`, and implements `failed()`.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.

- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

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

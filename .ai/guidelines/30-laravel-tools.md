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

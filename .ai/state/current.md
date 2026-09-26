# Current state (keep ≤ 50 lines; update at every checkpoint)

**Branch:** `feature/integrations` (Track A integration branch). **Updated:** 26-09-2026.

**Done (Track A, phase A0/A1):** API auth (Sanctum tokens, guards, middleware aliases), notifications &
settings API, BUG-104 booking columns, refund-details edit, dashboard permission, dead routes, roles =
designations, test isolation (`xlrm_testing`), PHP 8.4, dead-code purge (DEC-030), AI-context
consolidation (DEC-031).

**Next:**
1. Track B foundation: create `D:\laragon\www\xceler8` (Laravel 13, Filament 5, modular), CI, ETL skeleton.
2. Track A normalisation programme (A2): schema baseline → manifest → generator + Rector fixer → batches.
3. UAT fixes as they come (A3).

**Known pre-existing test failures:** 1 (`StandaloneUsersImportTest` — needs fixture `storage/user_data.xlsx`).
The Post-module tests were removed with the dead code (DEC-030).

**Waiting on the user:** rotate the Google service-account key (then history purge approval); ticket intake
channels (explanation given); SLA/retention (later); enabling data scoping; approval scope dimensions.

**Environment:** Laragon, PHP 8.4.26 (+redis), MySQL 8.4.3, Redis + Mailpit available.
`php artisan testing:refresh-db --force` after local schema/data changes.

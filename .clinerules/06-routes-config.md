# Routes & Config Reference Catalog

This catalog outlines all route files across `routes/` and key configuration files across `config/`.

---

## 1. Route Definitions (`routes/`)

| File | Prefix / Guard | Purpose & Main Endpoints |
|---|---|---|
| `routes/web.php` | `web` | Base landing redirects, health checks, and root dashboard redirects. |
| `routes/api.php` | `api` (Sanctum) | Public auth (`/api/v1/auth/request-otp`, `/verify-otp`), protected endpoints (`/me`, `/docs/*`, `/notifications/*`, `/vehicle/pricing/*`, `/settings`). |
| `routes/backpack/core.php` | `web`, Backpack auth | Core admin routes (`/admin/dashboard`, `/admin/branch`, `/admin/user`, `/admin/person`, `/admin/segment`, etc.). |
| `routes/backpack/pricing.php` | `web`, Backpack auth | Pricing pipeline routes (`/admin/pricing/workflow/*`, `/hold/*`, `/insurance/*`, `/rto/*`, `/tcs/*`, `/reset/*`). |
| `routes/backpack/booking.php` | `web`, Backpack auth | Commercial operations (`/admin/booking`, `/admin/lead`, `/admin/quotation`, `/admin/spare-request`, `/admin/hr`). |
| `routes/console.php` | CLI | Scheduled Artisan tasks, recurring pricing sync jobs, and cleanup commands. |

---

## 2. Configuration Catalog (`config/`)

| Config File | Purpose & Key Parameters |
|---|---|
| `config/pricing.php` | Vehicle pricing engine settings: `PRICING_PROCESS_LOG`, `PRICING_TCS_LIMIT` (default 10,00,000 INR), `PRICING_TCS_RATE` (default 1.0%), default insurance combinations (OD + TP + NilDep + Consumables), session timeout rules. |
| `config/backpack/base.php` | Backpack admin core settings: route prefix (`admin`), auth guard (`backpack`), title, project name, custom skin/theme. |
| `config/backpack/crud.php` | Default settings for CRUD operations (list entries, export buttons, bulk actions, responsive tables). |
| `config/column_transformations.php`| Model column mutator rules: automatic uppercase for codes/PAN/GST, lowercase for emails, slug generation rules. |
| `config/sanctum.php` | API authentication tokens, expiration lifetimes, stateful domains. |
| `config/firebase.php` | Firebase Cloud Messaging (FCM) credentials, server keys, project ID, and default notification channels. |
| `config/permission.php` | Spatie Laravel-Permission settings: role/permission table names, cache expiration (default 3600s). |
| `config/excel.php` | Maatwebsite / PhpSpreadsheet settings: chunk sizes, memory limits, CSV delimiters, sheet read filters. |
| `config/media-library.php` | Spatie Media Library settings: disk (`public` / `s3`), max file sizes, responsive image conversions. |
| `config/laradocs.php` | Laradocs documentation engine configuration. |

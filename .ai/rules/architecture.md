---
paths:
  - 'app/**'
  - 'routes/**'
  - 'config/**'
  - routes/api.php
---

# XCELR8 System Architecture

## 1. Stack (Active / Locked)

| Area | Active Standard |
|---|---|
| Framework | Laravel 12 |
| PHP | 8.3 (strict_types on every file) |
| Admin Panel | **Backpack 7** — do NOT generate Filament resources |
| Database | MySQL / MariaDB, InnoDB, `utf8mb4_unicode_ci` |
| Auth (web) | Session via Backpack middleware |
| Auth (API) | Laravel Sanctum + OTP via canonical `AuthService` |
| Permissions | `spatie/laravel-permission` |
| Media | `spatie/laravel-medialibrary` v11 on BaseModel |
| Excel | Maatwebsite Excel + PhpSpreadsheet (one-sheet load) |
| Queue | `php artisan queue:work --timeout=1800 --tries=1` |
| Cache | Redis or file; `Cache::flexible` / `Cache::remember` |
| Search | Laravel Scout (docs module) |
| Push | Firebase / Kreait FCM via `NotificationService` |
| AI Gateway | LiteLLM via `XCELR8_GATEWAY_BASE_URL` + `XCELR8_GATEWAY_MASTER_KEY` |
| Quality | Laravel Pint, PHPStan/Larastan, PHPUnit/Pest |
| Changelog | `markwalet/laravel-changelog` |
| Dev docs | `petebishwhip/laradocs` |

---

## 2. Layered Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                       HTTP Ingress                          │
│       Web (Admin Backpack)        REST API (Sanctum / OTP)  │
└──────────────────────────────┬──────────────────────────────┘
                               │
┌──────────────────────────────▼──────────────────────────────┐
│                    Middleware Pipeline                      │
│  Auth (Sanctum/Session) ➔ Device Validation ➔ Role / Scopes │
└──────────────────────────────┬──────────────────────────────┘
                               │
┌──────────────────────────────▼──────────────────────────────┐
│              Controller & Presentation Layer                │
│       Backpack CrudControllers        API Controllers       │
└──────────────────────────────┬──────────────────────────────┘
                               │
┌──────────────────────────────▼──────────────────────────────┐
│                       Service Layer                         │
│  OrgService ── PersonService ── RBACService ── PricingEngine│
│  DataScopeFilter ── HRJourneyService ── NotificationService │
└──────────────────────────────┬──────────────────────────────┘
                               │
┌──────────────────────────────▼──────────────────────────────┐
│                Domain Models & ORM (Eloquent)               │
│     User, Employee, Person, Branches, Org Hierarchy         │
│       Pivot Relations & Active Scope Interceptors           │
└──────────────────────────────┬──────────────────────────────┘
                               │
┌──────────────────────────────▼──────────────────────────────┐
│                Persistence & Infrastructure                 │
│   MySQL (Relational)  │  Cache (Redis/DB)  │  Queue (Async) │
│   Firebase Cloud Msg  │  LiteLLM Gateway   │  Media Storage │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. Request Lifecycle

1. **Routing & Dispatch**
   - Web: `routes/web.php` (loader) → `routes/{module}.php` files
   - Admin/Backpack: `routes/backpack/*.php`
   - API: `routes/api.php` prefixed `/api/v1`

2. **Authentication & Identity**
   - API: `auth:sanctum` + OTP validation + device tracking (`validate_device`)
   - Web/Admin: Backpack session auth

3. **Context Initialization & Data Scoping**
   - Authenticated user resolves to `Employee` and `Person` entities
   - Active `UserScope` evaluates access levels (Branch, Location, Department, Division)
   - `bypass_data_scoping = true` or `isSuperAdmin()` skips contextual filters

4. **Service Execution**
   - Controllers delegate ALL complex calculations and domain mutations to dedicated Services
   - Read-heavy queries check `Cache::remember()` before touching the database

5. **Response & Audit**
   - JSON responses via standard envelope (`success`, `message`, `data`)
   - Backpack blade views for admin UI
   - State mutations audited via `owen-it/laravel-auditing`

---

## 4. Module Directory Structure (Mandatory)

```
app/
├── Actions/               # Single-use action classes per Module/Process
│   └── {Module}/{Process}/
├── Exports/               # Laravel Excel exports per Module/Process
│   └── {Module}/{Process}/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/         # Backpack CrudControllers → Admin/{Module}/{Process}/
│   │   ├── Api/V1/        # API Controllers → Api/V1/{Module}/{Process}/
│   │   └── {Module}/{Process}/  # Web controllers
│   ├── Requests/          # FormRequests → {Module}/{Process}/
│   └── Resources/         # API Resources → {Module}/{Process}/
├── Imports/               # Laravel Excel imports per Module/Process
│   └── {Module}/{Process}/
├── Jobs/                  # Queued jobs → {Module}/{Process}/
├── Models/
│   ├── Admin/             # Branch, Location, Department, Person, ...
│   ├── Vehicle/           # Segment, SubSegment, VehicleModel, Variant, Accessory
│   │   └── Pricing/       # ImportSession, Profile, Pricing, Addon, Discount, ...
│   ├── IAM/               # OtpToken, DeviceSession, UserDeviceToken, ...
│   └── Utilities/         # Settings, KeyValue, Docs, Noty, Synonym
├── Services/
│   ├── IAM/               # AuthService, OtpService
│   ├── HR/                # HRJourneyService, UserReportingService
│   ├── Vehicle/           # VehicleService, VehicleMasterService
│   │   └── Pricing/       # PricingEngineService, ImportSessionService, ...
│   ├── Utils/             # SynonymService, SystemSettingService
│   ├── OrgService.php     # Hierarchy master — SSOT for org/branch/dept
│   ├── PersonService.php  # Person + contacts normalizer
│   └── RBACService.php    # Role + permission resolution
└── Core/ or Shared/       # Shared infrastructure (Notification, File, Audit, PDF)

resources/
├── views/
│   └── {module}/{process}/{activity}/index.blade.php
└── docs/
    └── modules/{module}/{process}/

routes/
├── web.php               # Loader only — require base_path('routes/{module}.php')
├── api.php               # API v1 loader
├── backpack/             # Backpack admin routes
├── pricing.php           # Pricing module routes
├── sales.php             # Sales module routes
└── {module}.php          # One file per business module
```

---

## 5. Subsystem Reference

### OrgService
SSOT for branch networks, departments, divisions, reporting managers, upline/downline traversal. Cache TTL = 3600. Never query org tables directly.

### PersonService
Decoupled person management. Handles phone normalization (Indian 10-digit), date parsing, primary contact/address designation. Never invent person regex — use this service.

### PricingEngineService
Vehicle pricing calculation, discount rules, RTO charges, insurance mappings, live quote generation. Heavy cache usage. SSOT for `getPricing(oemCode)`.

### AI Integration (LiteLLM Gateway)
Centralized proxy to LLM providers. Logical model routing:
- `AI_MODEL_FAST`: summaries, entity extraction
- `AI_MODEL_MULTIMODAL`: vision, document processing, OCR
- `AI_MODEL_ENTERPRISE`: high-reasoning, structured output
- `AI_MODEL_ROUTER`: dynamic routing
- `AI_MODEL_EDGE`: lightweight edge

### AuthService (canonical)
OTP: 6/10 min expiry; 5 req/15 min rate limit; 5 fails → 30 min lock; **device limit = 5**.

---

## 6. Naming Summary

| Artifact | Convention | Example |
|---|---|---|
| PHP classes | PascalCase | `BookingPaymentService` |
| Methods / variables | camelCase | `receivePayment()`, `$bookingId` |
| DB tables | snake_case plural with module prefix | `xcelr8_sales_bookings` |
| DB columns | snake_case | `booking_status`, `created_by` |
| Route names | dot.notation.kebab-case | `sales.booking.receive-payment` |
| Route URIs | kebab-case | `/sales/bookings/{booking}/receive-payment` |
| Permissions | UPPER_MODULE_PROCESS_ACTIVITY | `SLS_BKNG_RCPAY` |
| Env vars | SCREAMING_SNAKE_CASE | `XCELR8_GATEWAY_BASE_URL` |

## API route handler style
Use [Controller::class, 'method'] array syntax for API routes; reserve closures for one-off web/backpack routes.

## DRY/SSOT layering: data ops in Models, business logic in Services, thin Controllers
Every change must follow DRY and Single Source of Truth:
- **Models** own data-access operations on their own table/relations — scopes, accessors/mutators, relationship methods, simple per-row queries. A model should not reach into unrelated domains' business rules.
- **Services** own business logic, cross-model orchestration, and anything touching more than one model/table together (see `.ai/rules/services.md`'s SSOT service catalog — extend an existing service before writing a new one, never duplicate logic already covered there).
- **Controllers** stay thin: validate input (FormRequest), call one Service (or Model scope for a trivial read), shape the response. No calculations, no multi-step orchestration, no raw cross-model queries inline in a controller method.
- Never re-implement logic that already exists in a Model method or Service — grep for it first.

Also standing process (should already be followed every task, not just when explicitly asked):
- Double-check and test every change before considering it done (Larastan + the narrowest relevant test/HTTP round trip — see CLAUDE.md's Development Workflow).
- Log every change in `docs/refactor/ai-changelogs-DD-MM-YYYY.md` as you go; when the user asks to commit, first reconcile that day's changelog so its final entries actually match what's being committed (not stale mid-task notes) before writing the commit message.
- Keep `docs/refactor/known-bugs-report.md` current — check it before starting in an area, add entries the moment a new bug is found, never lose a finding by only mentioning it in chat.

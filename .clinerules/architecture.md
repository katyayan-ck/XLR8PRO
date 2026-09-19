<!--
# System Architecture Blueprint
# Scope: System design, Laravel flow patterns, and multi-layered loading
-->

# System Architecture

## 1. Overview & Core Philosophy
This system (XLRM / Xceler8) is an enterprise application built upon **Laravel 12**, **Backpack CRUD 7.0**, and PHP 8.2+.
The platform enforces a layered, domain-aware architecture designed for high throughput, strict role and post-based access controls, organizational data scoping, and automated integration with AI model gateways.

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
│  DataScopeService ── HRJourneyService ── NotificationService│
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

## 2. Multi-Layered Loading & Lifecycle Flow

### 2.1 Request Lifecycle Sequence
1. **Routing & Dispatch**:
   - Web requests route via `routes/web.php` and dynamic Backpack routes loaded via `routes/backpack/*.php`.
   - API requests route via `routes/api.php` prefixed under `/api/v1`.
2. **Authentication & Identity**:
   - API uses `auth:sanctum` combined with OTP validation and device tracking (`validate_device`).
   - Backpack uses session authentication via Backpack middleware.
3. **Context Initialization & Data Scoping**:
   - Current authenticated user resolves associated `Employee` and `Person` entities.
   - Active scopes (`UserScope`) evaluate access levels (Branch, Location, Department, Division).
   - Users with `bypass_data_scoping = true` or `isSuperAdmin()` bypass contextual scoping filters.
4. **Service Execution**:
   - Controllers delegate all complex calculations and domain mutations directly to dedicated Services.
   - Read-heavy queries check `Cache::remember()` tags/keys before touching the database.
5. **Response & Audit**:
   - Data formatted via standard JSON responses or Backpack blade views.
   - State mutations are audited using `owen-it/laravel-auditing`.

---

## 3. Subsystem Architecture

### 3.1 Organization & Hierarchy Engine (`OrgService`)
- **Responsibility**: Central source of truth for branch networks, departments, divisions, reporting managers, and upline/downline traversal.
- **Hierarchy Traversals**:
  * Upline tree navigation with visited node tracking to prevent circular loops.
  * Downline resolution supporting flat listing or hierarchical tree formats.
- **Caching Pattern**: High-frequency entities (branches, locations, departments) cached with a standard TTL (`CACHE_TTL = 3600`).

### 3.2 Person & Profile Subsystem (`PersonService`)
- **Responsibility**: Decoupled person management (`Person`, `PersonContact`, `PersonAddress`, `PersonBankingDetail`).
- **Attributes Normalization**: Cleans phone numbers (resolving country codes and standard 10-digit Indian formats), parses dates safely, and handles primary contact/address designations.

### 3.3 Pricing Engine (`PricingEngineService`)
- **Responsibility**: Vehicle pricing calculation, discount rules, RTO charges, insurance mappings, and live quote generation.
- **Caching**: Heavy reliance on cached price lists and model-variant matrices.

### 3.4 AI Integration Layer (LiteLLM Gateway)
- **Architecture**: Centralized proxy connecting application services to LLM providers via a local or hosted LiteLLM Gateway.
- **Gateway URL**: Configured via `XCELR8_GATEWAY_BASE_URL` and authorized with `XCELR8_GATEWAY_MASTER_KEY`.
- **Logical Model Routing**:
  * `AI_MODEL_FAST`: Low-latency tasks (summaries, entity extraction).
  * `AI_MODEL_MULTIMODAL`: Vision, document processing, and OCR.
  * `AI_MODEL_ENTERPRISE`: High-reasoning and structured output generation.
  * `AI_MODEL_ROUTER`: Dynamically routed requests.
  * `AI_MODEL_EDGE`: Lightweight edge execution.

---

## 4. Multi-Layered AI Knowledge System Integration
To ensure AI coding agents operate with full context and zero degradation of performance, knowledge is stratified into the `.ai/` directory:
- **Services & Dependency Injection**: See `.ai/services.md`
- **Query Optimization & Database Safety**: See `.ai/database.md`
- **Access Control Matrix**: See `.ai/rbac.md`
- **Data Isolation & Scopes**: See `.ai/scopes.md`
- **Code Quality & Style**: See `.ai/conventions.md`
- **Architectural Decisions**: See `.ai/decisions.md`
- **Known Traps & Edge Cases**: See `.ai/known-pitfalls.md`
*** End Patch
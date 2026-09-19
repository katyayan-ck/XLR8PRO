<!--
# Services Architecture & Dynamic Binding Guide
# Scope: Dynamic service class auto-binding, skill maps, and interface layers
-->

# Service Layer Architecture

## 1. Overview
The service layer encapsulates business logic, organizational computation, pricing algorithms, third-party integrations, and identity access rules. Controllers must remain thin, delegating domain logic to service classes.

---

## 2. Service Registration & Binding Patterns

### 2.1 Provider Configuration (`AppServiceProvider`)
Service bindings are centralized in `app/Providers/AppServiceProvider.php`. Services are categorized by lifecycle:

```php
// 1. Singleton Services (Stateful or heavy initialization)
$this->app->singleton(RBACService::class, fn($app) => new RBACService());
$this->app->singleton(ApprovalService::class, fn($app) => new ApprovalService());
$this->app->singleton(FirebaseService::class, fn($app) => new FirebaseService());
$this->app->singleton(NotificationService::class, fn($app) => new NotificationService(
    $app->make(FirebaseService::class)
));
$this->app->singleton(\App\Services\IAM\PostService::class);
$this->app->singleton(\App\Services\IAM\ReportingService::class);
$this->app->singleton(\App\Services\HR\HRJourneyService::class);

// 2. Transient / Request-Scoped Services
$this->app->bind(AuthService::class, function ($app) {
    return new AuthService(
        $app->make(\Illuminate\Http\Request::class),
        $app->make(\Illuminate\Cache\CacheManager::class),
        $app->make(\App\Services\OtpNotificationService::class)
    );
});
```

### 2.2 Auto-Binding Guidelines
- When creating new domain services under `App\Services`, register them in `AppServiceProvider::register()`.
- Use `singleton` for stateless services or cache-backed coordinators to avoid redundant instantiation.
- Inject services into Controller constructors or methods using Laravel's dependency injection container.

---

## 3. Core Service Registry & Skill Maps

| Service | Primary Namespace | Core Responsibilities | Caching Strategy |
|---|---|---|---|
| `OrgService` | `App\Services\OrgService` | Branches, locations, departments, divisions, hierarchy upline/downline, user queries | `Cache::remember(..., 3600)` with targeted keys (`org.branches`, `org.locations.{code}`) |
| `PersonService` | `App\Services\PersonService` | Person records, contacts, addresses, banking details, search, phone normalization | Dynamic query builder, transaction-wrapped mutations |
| `RBACService` | `App\Services\RBACService` | Spatie permission aggregation, post-based permissions, temporal roles, wildcard SuperAdmin check | `Cache::remember("user.{id}.permissions", 3600)` |
| `PricingEngineService` | `App\Services\Vehicle\Pricing` | Base price calculation, insurance add-ons, RTO fees, discounts, exchange appraisals | Matrix cache with versioned pricing models |
| `FirebaseService` | `App\Services\FirebaseService` | Firebase Admin SDK integration, FCM push messaging | Cached credentials token |
| `NotificationService` | `App\Services\NotificationService` | Notification dispatch, device registration, in-app alerts | Database queue table dispatch |
| `ApprovalService` | `App\Services\ApprovalService` | Multi-tier workflow authorization, state transitions | Audit logged state transitions |

---

## 4. Service Interface Contracts & Conventions

### 4.1 Method Signatures & Typing
- All service methods must use strict scalar types and explicit return types (`void`, `?array`, `Collection`, `bool`, etc.).
- Avoid leaking raw HTTP requests into services; pass validated data arrays or DTOs.

```php
// Standard Service Pattern
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FeatureService
{
    public function executeAction(array $validatedData, int $userId): array
    {
        return DB::transaction(function () use ($validatedData, $userId) {
            // Domain logic
            return ['status' => 'success', 'data' => []];
        });
    }
}
```

### 4.2 Error Handling & Logging
- Never swallow critical exceptions silently. Wrap external integrations in `try-catch` blocks and log with actionable context:
```php
try {
    // External or high-risk execution
} catch (Throwable $e) {
    Log::error('Service action failed', [
        'service' => static::class,
        'error'   => $e->getMessage(),
        'trace'   => $e->getTraceAsString(),
    ]);
    throw $e;
}
```
*** End Patch
*** Add File: d:\laragon\www\xlrm\.ai\database.md
<!--
# Database Standards & SQL Optimization
# Scope: SQL optimization rules, Eloquent query safety, and schema guidelines
-->

# Database Standards & Eloquent Safety

## 1. Schema Design Guidelines

### 1.1 Table & Column Naming
- **Tables**: Lowercase plural with snake_case. Core organizational and pivot tables use the prefix `xlr8_` (e.g., `xlr8_admin_emp_branch_pivot`, `xlr8_system_jobs`). Standard Laravel domain tables follow conventions (`users`, `roles`, `permissions`).
- **Primary Keys**: Use auto-incrementing `id` (`bigIncrements`) unless designated a strict code-based domain key (`person_code`, `employee_code`).
- **Foreign Keys**: Suffix with `_id` for integer references (`user_id`, `role_id`) or `_code` for organizational code references (`branch_code`, `dept_code`, `div_code`).
- **Booleans**: Name with `is_`, `has_`, or `bypasses_` prefixes (e.g., `is_active`, `bypass_data_scoping`).
- **Auditing Timestamps**: Every table must include standard `created_at`, `updated_at`, and nullable `deleted_at` for soft deletes. Include `created_by`, `updated_by`, `deleted_by` where tracking is mandatory.

### 1.2 Indexing Strategy
- Always index foreign keys and columns used in filtering, joining, or sorting:
  * Single-column indexes on high-frequency lookups (`employee_code`, `person_code`, `is_active`).
  * Composite indexes on common scoping queries (e.g., `['scope_type', 'scope_code', 'is_active']`).
  * Unique indexes on identity pairs in pivot tables:
```php
$table->unique(['employee_code', 'branch_code'], 'emp_branch_unique');
```

---

## 2. Eloquent Safety Rules

### 2.1 Prevention of N+1 Queries
- **Never** access relations inside loops without eager loading.
- Always specify needed relations using `with()`:
```php
// CORRECT
$users = User::with(['employee.designation', 'person'])->get();

// FORBIDDEN
$users = User::all();
foreach ($users as $user) {
    echo $user->employee->designation->name; // Causes N+1 queries
}
```

### 2.2 Memory Safety on Large Datasets
- **Never** execute `all()` or unconstrained `get()` on tables that grow indefinitely (`users`, `audit_logs`, `booking_records`).
- Use `chunkById()` or lazy collections for background tasks, batch exports, or migrations:
```php
User::where('is_active', true)->chunkById(200, function ($users) {
    foreach ($users as $user) {
        // Process batch
    }
});
```

### 2.3 Mass Assignment & Guarded Properties
- Explicitly define `$fillable` on all models.
- Ensure sensitive columns (`is_admin`, `bypass_data_scoping`, `password`) are never exposed to uncontrolled mass-assignment.

---

## 3. Transaction Management & Query Integrity

### 3.1 Database Transactions
- Any operation mutating multiple tables (e.g., creating a `User` alongside `Employee` and `Person` records) **must** be enclosed in `DB::transaction()`:
```php
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($userData, $personData) {
    $person = Person::create($personData);
    $user = User::create(array_merge($userData, ['person_code' => $person->person_code]));
    return $user;
});
```

### 3.2 Raw Query Safeguards
- Direct concatenation of user input into raw SQL queries is strictly prohibited.
- Always use PDO parameter binding:
```php
// CORRECT
User::whereRaw('LOWER(username) = ?', [strtolower($username)])->first();

// FORBIDDEN
User::whereRaw("LOWER(username) = '" . strtolower($username) . "'")->first();
```
*** End Patch
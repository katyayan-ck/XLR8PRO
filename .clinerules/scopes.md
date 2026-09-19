<!--
# Data Scoping & Contextual Isolation
# Scope: Contextual variable isolation and execution scopes
-->

# Data Scoping & Contextual Isolation

## 1. Overview
Data scoping restricts which business entities (branches, locations, departments, divisions, records) a user can read or manipulate based on their operational assignment. Unlike RBAC (which dictates *actions* a user can perform), Data Scoping dictates *which records* a user can access.

---

## 2. Scope Types & Dimensions

Scopes are evaluated across 5 primary dimensions:
1. **BRANCH**: Dealership branch codes (`BR01`, `BR02`, etc.)
2. **LOCATION**: Physical sub-locations attached to branches (`LOC-NORTH`, etc.)
3. **DEPARTMENT**: Organizational units (`SALES`, `SERVICE`, `ACCOUNTS`, `HR`)
4. **DIVISION**: Operational divisions within departments (`RETAIL`, `CORPORATE`)
5. **VERTICAL**: High-level business verticals (`PASSENGER`, `COMMERCIAL`)

---

## 3. Scoping Rules on the User Model

### 3.1 Primary vs Secondary Scopes
- Every `Employee` possesses primary assignments:
  * `primary_branch_code`
  * `primary_loc_code`
  * `primary_dept_code`
  * `primary_div_code`
- Multi-scope access is granted via pivot tables:
  * `xlr8_admin_emp_branch_pivot`
  * `xlr8_admin_emp_location_pivot`
  * `xlr8_admin_emp_department_pivot`
  * `xlr8_admin_emp_division_pivot`

### 3.2 Dynamic Scope Checks
```php
// Checks if user has access to a specific scope code
$hasAccess = $user->hasScope('BRANCH', 'BR01');

// Retrieve all active codes for a scope type
$branches = $user->getScopeCodes('BRANCH'); // ['BR01', 'BR02']

// Retrieve full matrix of active scopes
$allScopes = $user->getAllScopes(); 
// ['BRANCH' => [...], 'DEPARTMENT' => [...]]
```

### 3.3 Scope Bypass Flag
- Users with `$user->bypass_data_scoping === true` or `$user->isSuperAdmin()` bypass all data scoping filters.
- Query interceptors and repository methods must check `$user->bypassesDataScoping()` before applying scope constraints.

---

## 4. Query Application & Scope Interceptors

### 4.1 Applying Scopes in Queries
When retrieving scoped entities (e.g., quotations, leads, inventory), services must apply scoping filters:

```php
public function getVisibleBookings(User $user): Builder
{
    $query = Booking::query();

    if ($user->bypassesDataScoping()) {
        return $query;
    }

    $allowedBranches = $user->getScopeCodes('BRANCH');
    if (!empty($allowedBranches)) {
        $query->whereIn('branch_code', $allowedBranches);
    }

    return $query;
}
```

### 4.2 Hierarchy Scoping (Downline / Upline)
- For sales performance and user visibility, managers see their downline tree using `OrgService::getDownline($username, $flat = true)`.
- Circular relationships must be guarded with depth limits (`maxDepth = 20`) and visited node tracking to avoid stack overflow.
*** End Patch
*** Add File: d:\laragon\www\xlrm\.ai\conventions.md
<!--
# Code Conventions & Style Guide
# Scope: Coding style, naming conventions, and file structures
-->

# Code Conventions & Architecture Standards

## 1. PHP & Laravel Standards

### 1.1 Strict Typing & PSR
- Adhere strictly to **PSR-12** code styling.
- Declare strict types at the top of every PHP file:
```php
<?php

declare(strict_types=1);
```
- Explicitly type-hint all function parameters, return types, and class properties.

### 1.2 Naming Conventions
| Artifact | Convention | Example |
|---|---|---|
| Model Classes | `PascalCase` (singular) | `User`, `VehicleModel`, `PersonAddress` |
| Service Classes | `PascalCase` + `Service` suffix | `OrgService`, `PricingEngineService` |
| Controller Classes | `PascalCase` + `Controller` suffix | `PricingController`, `DocController` |
| Backpack CrudControllers | `PascalCase` + `CrudController` suffix | `BranchCrudController`, `UserCrudController` |
| Database Tables | `snake_case` (plural), prefixed with `xlr8_` for core pivots/jobs | `users`, `xlr8_admin_emp_branch_pivot` |
| Database Columns | `snake_case` | `employee_code`, `is_active`, `deleted_at` |
| Route Names | Dot notation `kebab-case` | `api.v1.vehicle.pricing.show` |
| Config Keys | `snake_case` | `config('services.firebase.project_id')` |
| Environment Variables | `SCREAMING_SNAKE_CASE` | `XCELR8_GATEWAY_BASE_URL` |

---

## 2. Directory & Namespace Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/            # Backpack CRUD & Admin Controllers
│   │   └── Api/V1/           # Versioned REST API Controllers
│   ├── Middleware/           # Device validation, Scope interceptors
│   └── Requests/             # FormRequest validation classes
├── Models/
│   ├── Admin/                # Branch, Location, Department, Person
│   ├── Vehicle/              # Model, Variant, Color, Pricing
│   └── User.php              # Central Authenticatable Model
└── Services/
    ├── IAM/                  # Identity & Access Management services
    ├── HR/                   # HR Journey & Post services
    ├── Vehicle/Pricing/      # Pricing engine & calculation services
    ├── OrgService.php        # Hierarchy & Organizational query master
    ├── PersonService.php     # Person and profile normalizer
    └── RBACService.php       # Role and permission resolution
```

---

## 3. Controller Best Practices

### 3.1 Keep Controllers Thin
- Controllers are HTTP adaptors: they validate requests, invoke services, and return responses.
- Never write inline SQL queries or complex multi-table business algorithms directly inside a Controller.

### 3.2 Form Requests for Validation
- Never use `$request->validate()` inline inside controller action methods.
- Encapsulate validation rules and authorization checks inside dedicated `FormRequest` classes under `app/Http/Requests`.

### 3.3 Consistent API Responses
- Standardize API responses using consistent envelope format:
```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": { ... }
}
```
*** End Patch
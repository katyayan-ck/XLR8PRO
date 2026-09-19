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
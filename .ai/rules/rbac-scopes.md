---
description: RBAC permission resolution, data scoping, OrgScope. Load for any authorization, user access, or scope filter work.
paths:
  - app/Services/RBACService.php
  - app/Services/OrgScopeService.php
  - app/Services/DataScopeFilter.php
  - app/Http/Middleware/**
  - app/Models/IAM/**
---

# XCELR8 RBAC & Data Scoping Rules

## 1. Permission System

**Package:** `spatie/laravel-permission`  
**Format:** `MODULE_PROCESS_ACTIVITY` (uppercase, underscore-separated)

Examples:
```
SLS_BKNG_CREATE       Sales → Booking → Create
SLS_BKNG_EDIT         Sales → Booking → Edit
SLS_BKNG_VIEW         Sales → Booking → View
SLS_BKNG_RCPAY        Sales → Booking → Receive Payment
SLS_BKNG_CANCEL       Sales → Booking → Cancel
PRC_VEH_MANAGE        Pricing → Vehicle → Manage (maps to manage_pricing)
```

Rules:
- Permission code is the **permanent technical identifier** — display names may change, codes must NOT
- **Never use generic permissions**: `create`, `edit`, `view` — always module-qualified
- `manage_pricing` = required permission for ALL pricing admin routes
- `SuperAdmin` = wildcard (bypasses all checks via `isSuperAdmin()`)
- Temporal role assignments via `UserRoleAssignment` model

---

## 2. Role Architecture

```
User
 └── UserRoleAssignment (temporal)
      └── Role (Spatie)
           └── Permission (Spatie) — format: MODULE_PROCESS_ACTIVITY

User
 └── Designation (maps to effective role in OrgScope)
      └── Reporting Manager (for approval power resolution)
```

Designation acts as approval-level role in the Approval Engine (when built).

---

## 3. Data Scoping — SSOT

### `DataScopeFilter` / `ScopedQuery`
Applied on models that should hide rows by user assignment.

Rules:
```php
// ✅ Always check bypass first
if ($user->bypassesDataScoping() || $user->isSuperAdmin()) {
    return $query; // no scope filters
}

// Apply scope filters
return $query->whereIn('branch_code', $user->allowedBranches());
```

### `OrgScopeService`
Resolves which branches/locations/divisions a user can access.  
Source: `xlr8_admin_user_scopes` table.

Scope dimensions:
- Branch
- Location
- Department
- Division
- Segment (for vehicle/pricing access)
- Model (for vehicle access)

### Jobs MUST bypass data scope
```php
// ✅ In jobs and background processes
MyModel::withoutDataScope()->where('status', 'PENDING')->chunk(100, function ($items) {
    // process
});
```

---

## 4. Route Authorization

Attach permission to route via middleware:

```php
Route::post('/{booking}/receive-payment', [BookingPaymentController::class, 'receivePayment'])
    ->middleware('permission:SLS_BKNG_RCPAY')
    ->name('receive-payment');
```

Or via `authorize()` in FormRequest:

```php
class ReceiveBookingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('SLS_BKNG_RCPAY');
    }
}
```

---

## 5. Pricing Route Authorization

All pricing management routes require `manage_pricing`:

```php
Route::prefix('pricing')
    ->name('pricing.')
    ->middleware(['auth', 'permission:manage_pricing'])
    ->group(function () {
        // pricing routes
    });
```

---

## 6. Permission vs Route Name

These are distinct concepts — never conflate:

| Concept | Format | Example |
|---|---|---|
| Permission | `MODULE_PROCESS_ACTIVITY` | `SLS_BKNG_RCPAY` |
| Route name | `module.process.activity` | `sales.booking.receive-payment` |
| Route URI | `kebab-case` | `/sales/bookings/{booking}/receive-payment` |

---

## 7. Device Management (Auth)

From `AuthService`:
- **Device limit = 5 per user**
- Device session tracking via `xlr8_iam_*` tables
- OTP: 6 min (web) / 10 min (API)
- Rate limit: 5 OTP requests per 15 min
- Failed attempts: 5 → 30 min lock

---

## 8. API Authorization Header

Sanctum bearer token required for all API routes:

```http
Authorization: Bearer {token}
```

Standard error responses:
```json
{"success": false, "message": "Unauthenticated.", "errors": {}}   // 401
{"success": false, "message": "Permission denied.", "errors": {}}  // 403
```

---

## 9. Backpack Admin Authorization

Backpack admin routes use `backpack.auth` middleware + custom `EnsureUserHasPermission` middleware:

```php
// In CrudController
public function setup()
{
    CRUD::setModel(Booking::class);
    CRUD::setRoute(config('backpack.base.route_prefix') . '/sales/booking');
    CRUD::setEntityNameStrings('booking', 'bookings');

    // Deny access if missing permission
    $this->middleware('permission:SLS_BKNG_VIEW');
}
```

---

## 10. Checking Permissions in Code

```php
// ✅ Check in controller / policy
if (! $request->user()->can('SLS_BKNG_RCPAY')) {
    abort(403, 'Permission denied.');
}

// ✅ Use RBACService for complex resolution
$canManagePricing = app(RBACService::class)->userHasPermission($user, 'manage_pricing');

// ✅ Gate check
Gate::authorize('SLS_BKNG_RCPAY');

// ❌ Never hardcode user IDs or role names
if ($user->id === 1) { /* super admin hack */ } // WRONG
if ($user->role === 'admin') { } // WRONG — use Spatie
```

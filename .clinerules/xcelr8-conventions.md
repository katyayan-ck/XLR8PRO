# Xcelr8 Coding, Architecture, Quality & AI Audit Conventions

> **Purpose:** This document is the condensed, enforceable development and audit standard for the Xcelr8 Laravel application.
>
> It combines the project-specific architectural rules with the AI/Cline safeguards required for repository analysis and implementation.
>
> The full project rules document remains the detailed reference. This file is the compact working contract for Cline and developers.
>
> **Audit scope:** When performing a repository-wide standards audit using this file, inspect the complete application/codebase while **excluding the `vendor/` directory**. Do not treat code inside `vendor/` as Xcelr8 source code or report its standards violations.

---

# 1. Core Architectural Principle

Xcelr8 is a modular enterprise Laravel application.

Every business capability must have clear ownership through:

```text
Module
    └── Process
          └── Activity
```

This ownership should remain traceable across:

- Permissions
- Modules
- Processes
- Activities
- Routes
- Route names
- Route files
- Controllers
- API Controllers
- Form Requests
- Models
- Services
- Actions
- Jobs
- Events
- Listeners
- Imports
- Exports
- Notifications
- Blade views
- Components
- Database tables
- Database columns
- APIs
- Swagger/OpenAPI
- Documentation
- Tests
- Git branches
- Pull requests
- Changelog entries

### Golden principle

> A developer should be able to determine which business module owns an artifact without inspecting unrelated code.

---

# 2. Module → Process → Activity

Every business function should belong to a Module and Process.

Activities represent specific operations.

Example:

```text
Sales
    └── Booking
          ├── Create
          ├── Edit
          ├── View
          ├── Receive Payment
          ├── Confirm Receipt
          └── Cancel
```

A capability should map consistently through the architecture.

Example:

```text
Module:
Sales

Process:
Booking

Activity:
Receive Payment

Permission:
SLS_BKNG_RCPAY

Route:
sales.booking.receive-payment

Controller:
BookingPaymentController

Service:
BookingPaymentService

Action:
ReceiveBookingPaymentAction

Model:
BookingPayment

Table:
xcelr8_sales_booking_payments

API:
POST /api/v1/sales/bookings/{booking}/receive-payment

View:
sales/booking/receive-payment/

Documentation:
Sales → Booking → Receive Payment
```

---

# 3. Module and Process Codes

Module codes must be unique.

Process codes must be unique within the project/module structure.

Activity codes must clearly identify the permitted operation.

Codes should:

- Be uppercase.
- Use letters/numbers only unless the existing project master explicitly requires another form.
- Contain no spaces.
- Remain stable once established.
- Match the project's registered Module/Process/Activity definitions.

Example:

```text
Module:   SLS
Process:  BKNG
Activity: RCPAY
```

---

# 4. Permission Naming

Canonical format:

```text
MODULE_PROCESS_ACTIVITY
```

Example:

```text
SLS_BKNG_RCPAY
```

Examples:

```text
SLS_BKNG_CREATE
SLS_BKNG_EDIT
SLS_BKNG_VIEW
SLS_BKNG_APPROVE
SLS_BKNG_RCPAY
SLS_BKNG_CONFIRM_RECEIPT
SLS_BKNG_CANCEL
```

Permission codes are stable technical identifiers.

Do not use ambiguous generic permissions such as:

```text
create
edit
view
delete
approve
```

unless they are part of an explicitly established framework-level mechanism rather than a business permission.

---

# 5. Permission vs Route

Permissions and routes are different technical concepts.

Permission:

```text
SLS_BKNG_RCPAY
```

Route name:

```text
sales.booking.receive-payment
```

URI:

```text
POST /sales/bookings/{booking}/receive-payment
```

The traceability should remain:

```text
Permission
    ↓
Route
    ↓
Controller
    ↓
Service / Action
```

Do not use route names as permission identifiers.

Do not use permission codes as route names.

---

# 6. PHP and Laravel Standards

Follow PSR-12.

New PHP files should normally begin with:

```php
<?php

declare(strict_types=1);
```

Prefer explicit types for:

- Method parameters
- Return types
- Properties
- Public APIs
- Service contracts
- Action contracts

Example:

```php
public function receivePayment(
    Booking $booking,
    float $amount
): BookingPayment
{
    //
}
```

Use the project's actual Laravel version and established framework patterns.

Do not invent incompatible framework conventions.

---

# 7. PHP Naming

### Classes

Use PascalCase.

Examples:

```text
User
VehicleModel
PersonAddress
OrgService
BookingPaymentService
BookingController
ReceiveBookingPaymentAction
StoreBookingRequest
```

### Methods

Use camelCase.

Examples:

```text
receivePayment()
confirmReceipt()
calculatePrice()
resolveScope()
```

### Variables

Use camelCase.

Examples:

```text
$bookingPayment
$vehicleVariant
$organizationScope
```

Follow established project naming for constants.

---

# 8. Directory and Namespace Consistency

Namespace must match the physical directory structure.

Example:

```text
app/Services/Sales/Booking/BookingPaymentService.php
```

must use:

```php
namespace App\Services\Sales\Booking;
```

Example:

```text
app/Models/Sales/Booking/Booking.php
```

must use:

```php
namespace App\Models\Sales\Booking;
```

Example:

```text
app/Http/Controllers/Api/Sales/Booking/BookingController.php
```

must use:

```php
namespace App\Http\Controllers\Api\Sales\Booking;
```

Report mismatches between physical location and namespace.

Do not create exceptions merely for personal preference.

---

# 9. Module Directory Structure

Preferred structure where compatible with the existing application:

```text
app/
├── Actions/
│   └── {Module}/
├── Exports/
│   └── {Module}/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   └── {Module}/
│   │   └── {Module}/
│   ├── Requests/
│   │   └── {Module}/
│   └── Resources/
│       └── {Module}/
├── Imports/
│   └── {Module}/
├── Jobs/
│   └── {Module}/
├── Models/
│   └── {Module}/
└── Services/
    └── {Module}/

resources/
├── views/
│   └── {module}/
└── docs/
    └── {module}/

routes/
├── web.php
├── api.php
└── {module}.php
```

The exact structure may follow established project patterns where they are deliberate and internally consistent.

---

# 10. Module Ownership

Every business artifact should have one clear owning module.

Before creating a class/file, determine:

```text
Which Module owns this?
Which Process owns this?
Which Activity does this represent?
```

When several modules consume functionality:

- Keep domain logic in its legitimate owning module.
- Place genuinely shared infrastructure in the established Core/Shared/Support area.
- Do not duplicate domain logic.
- Do not move code to Shared merely because ownership is inconvenient.

---

# 11. Shared / Core / Support Code

Shared infrastructure may live in the project's established shared/core areas.

Examples:

```text
NotificationService
FileStorageService
AuditService
EncryptionService
PdfService
```

Shared areas must not become generic dumping grounds.

A utility should be considered shared because it is genuinely reusable, not because it is difficult to classify.

---

# 12. Controllers

Controllers are HTTP adapters.

Controllers should:

- Receive requests.
- Perform appropriate authorization.
- Delegate validation to Form Requests where appropriate.
- Invoke Services/Actions.
- Return the response.

Controllers should remain thin.

Avoid putting into controllers:

- Complex business logic
- Large multi-table algorithms
- Repeated business rules
- Scope-resolution algorithms
- Complex RBAC calculations
- Large reusable data transformations
- Significant database workflows

Preferred:

```php
public function store(StoreBookingRequest $request): JsonResponse
{
    $booking = $this->bookingService->create(
        $request->validated()
    );

    return response()->json([
        'success' => true,
        'message' => 'Booking created successfully.',
        'data' => $booking,
    ]);
}
```

---

# 13. Form Requests

Use dedicated Form Request classes for non-trivial request validation.

Preferred:

```text
app/Http/Requests/Sales/Booking/StoreBookingRequest.php
app/Http/Requests/Sales/Booking/UpdateBookingRequest.php
app/Http/Requests/Sales/Booking/ReceiveBookingPaymentRequest.php
```

Form Requests should handle:

- Validation rules
- Request-specific authorization
- Input validation concerns

They should not become business-workflow containers.

---

# 14. Models

Models must remain domain-oriented.

Prefer:

```text
RtoRule
Booking
BookingPayment
BookingReceipt
```

Avoid names based purely on implementation/table wording such as:

```text
Xcelr8PricingRtoRulesModel
SalesBookingModel
```

Models may contain appropriate:

- Relationships
- Casts
- Attributes
- Query scopes
- Domain-specific model behavior

Do not use models as general workflow orchestration containers.

---

# 15. Services

Services should follow Module → Process ownership.

Examples:

```text
app/Services/Sales/Booking/BookingService.php
app/Services/Sales/Booking/BookingPaymentService.php
app/Services/Sales/Booking/BookingReceiptService.php
```

Services should contain reusable domain/business logic appropriate to the service layer.

Avoid:

- Duplicating the same rule in multiple services.
- Artificial generic services.
- Services with unrelated responsibilities.
- Service placement that obscures business ownership.

---

# 16. Actions

Use dedicated Actions for meaningful, focused business operations where the project architecture calls for them.

Example:

```text
app/Actions/Sales/Booking/
├── CreateBookingAction.php
├── UpdateBookingAction.php
├── ReceiveBookingPaymentAction.php
├── ConfirmBookingReceiptAction.php
└── CancelBookingAction.php
```

An Action should represent a meaningful operation.

Do not create Action classes solely as artificial wrappers around trivial one-line calls.

---

# 17. Jobs

Jobs should remain module-aware.

Example:

```text
app/Jobs/Sales/Booking/
├── GenerateBookingInvoiceJob.php
├── SendBookingConfirmationJob.php
└── SendPaymentReceiptJob.php
```

Jobs should delegate domain behavior to Services/Actions rather than duplicating business rules.

---

# 18. Events and Listeners

Module-specific events and listeners should have clear module ownership.

Avoid putting module-specific listeners into ambiguous global locations without justification.

Listeners should not silently become large, hidden business-logic containers.

---

# 19. Imports and Exports

Imports and Exports should remain module-aware.

Examples:

```text
app/Imports/Sales/Booking/BookingImport.php
app/Exports/Sales/Booking/BookingExport.php
```

Do not place module-specific imports/exports into generic global directories where ownership becomes unclear.

---

# 20. Routes

Each major business module should have its own route file where this architecture is used.

Example:

```text
routes/
├── web.php
├── api.php
├── sales.php
├── pricing.php
├── inventory.php
└── billing.php
```

Module route files should contain routes belonging to that module.

Central route files should primarily load module route definitions where the project follows this pattern.

Do not create a giant centralized business route file.

---

# 21. Route URI Naming

Use:

- lowercase
- kebab-case
- descriptive business terminology

Preferred:

```text
/sales/bookings
/sales/bookings/create
/sales/bookings/{booking}
/sales/bookings/{booking}/edit
/sales/bookings/{booking}/receive-payment
/sales/bookings/{booking}/confirm-receipt
```

Avoid:

```text
/Sales/Booking
/salesBooking
/sales_booking
/sales/Booking/Create
```

---

# 22. Route Name Standard

Use:

```text
module.process.activity
```

Examples:

```text
sales.booking.index
sales.booking.create
sales.booking.store
sales.booking.show
sales.booking.edit
sales.booking.update
sales.booking.receive-payment
sales.booking.confirm-receipt
sales.booking.cancel
```

Admin routes should remain explicit where applicable:

```text
admin.sales.booking.index
admin.sales.booking.create
admin.sales.booking.receive-payment
```

Route names should be:

- lowercase
- dot-separated
- module-identifiable
- process-identifiable
- activity-identifiable

---

# 23. Route Authorization

Authorization must follow the project's RBAC implementation.

Example:

```php
Route::post(
    '/{booking}/receive-payment',
    [BookingPaymentController::class, 'receivePayment']
)
    ->middleware('permission:SLS_BKNG_RCPAY')
    ->name('receive-payment');
```

Traceability should remain:

```text
Route
    ↓
Permission
    ↓
Controller
    ↓
Service / Action
```

Do not remove authorization merely to simplify implementation.

---

# 24. API Controllers

API controllers follow the same Module → Process → Activity ownership model.

Example:

```text
app/Http/Controllers/Api/Sales/Booking/
├── BookingController.php
└── BookingPaymentController.php
```

API URLs should be versioned where required by the project:

```text
/api/v1/sales/bookings
/api/v1/sales/bookings/{booking}
/api/v1/sales/bookings/{booking}/receive-payment
```

---

# 25. API Design

Every API should make its ownership and contract discoverable.

Document:

```text
Module
Process
Activity
Permission
HTTP Method
URI
Route Name
Controller
Controller Method
Authentication
Authorization
Request
Response
Errors
```

Do not create inconsistent endpoint structures between modules.

---

# 26. API Response Standard

Use the project's established response contract.

Success:

```json
{
    "success": true,
    "message": "Operation completed successfully.",
    "data": {}
}
```

Error:

```json
{
    "success": false,
    "message": "Validation failed.",
    "errors": {}
}
```

Do not invent a different response contract inside an individual module without architectural justification.

---

# 27. Swagger / OpenAPI

Every applicable API endpoint must have appropriate Swagger/OpenAPI documentation.

Document:

- Module
- Process
- Activity
- Permission
- HTTP method
- URI
- Route name
- Controller
- Controller method
- Authentication
- Authorization
- Path parameters
- Query parameters
- Headers
- Request body
- Required fields
- Optional fields
- Data types
- Examples
- Success responses
- Validation errors
- Authentication errors
- Authorization errors
- Not-found errors
- Relevant business-rule errors

The documentation should be sufficient for another developer to consume the API without reading implementation code.

---

# 28. Blade View Structure

Views should mirror Module → Process → Activity.

Example:

```text
resources/views/sales/booking/
├── create/
│   └── index.blade.php
├── edit/
│   └── index.blade.php
├── list/
│   └── index.blade.php
├── view/
│   └── index.blade.php
└── receive-payment/
    └── index.blade.php
```

Prefer meaningful activity-specific paths.

---

# 29. Blade Components

Module-specific components should remain within their module where practical.

Examples:

```text
resources/views/components/sales/booking/
├── booking-status.blade.php
├── booking-summary.blade.php
└── payment-summary.blade.php
```

Only genuinely reusable application-wide components belong in generic shared component areas.

---

# 30. Database Table Naming

Where Xcelr8 module naming applies, prefer:

```text
xcelr8_{module}_{entity}
```

or:

```text
xcelr8_{module}_{process}_{entity}
```

Examples:

```text
xcelr8_pricing_rto_rules
xcelr8_pricing_rto_values
xcelr8_pricing_rto_vehicle_mapping

xcelr8_sales_bookings
xcelr8_sales_booking_payments
xcelr8_sales_booking_receipts
xcelr8_sales_enquiries
xcelr8_sales_quotations
xcelr8_sales_bills
```

Table names should:

- Be lowercase.
- Use snake_case.
- Clearly identify business ownership.
- Avoid unnecessary abbreviations.
- Follow established project exceptions when they are intentional.

---

# 31. Database Columns

Use snake_case.

Examples:

```text
employee_code
is_active
deleted_at
booking_date
customer_id
vehicle_id
rto_rule_id
```

Avoid ambiguous abbreviations unless they are established project conventions.

---

# 32. Foreign Keys

Foreign keys should be descriptive.

Preferred:

```text
booking_id
customer_id
vehicle_id
rto_rule_id
```

Table prefixes do not change foreign-key naming.

---

# 33. Migrations

Migration names must clearly describe the schema operation.

Examples:

```text
create_xcelr8_pricing_rto_rules_table
create_xcelr8_sales_bookings_table
add_status_to_xcelr8_sales_bookings_table
add_approved_at_to_xcelr8_sales_quotations_table
```

Inspect actual schema usage before reporting a migration/schema problem.

---

# 34. Backpack CRUD

Backpack CRUD controllers must follow module/process ownership.

Example:

```text
app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php
```

with:

```php
namespace App\Http\Controllers\Admin\Sales\Booking;

class BookingCrudController extends CrudController
{
    //
}
```

Backpack routes should remain module-specific.

---

# 35. Documentation / Laradocs

Significant modules should maintain developer documentation.

Preferred structure:

```text
resources/docs/
├── _index.md
├── architecture/
├── modules/
│   ├── sales/
│   │   ├── _index.md
│   │   └── booking/
│   │       ├── _index.md
│   │       ├── overview.md
│   │       ├── permissions.md
│   │       ├── routes.md
│   │       ├── services.md
│   │       ├── api.md
│   │       └── database.md
│   └── pricing/
└── development/
```

Module documentation should cover:

- Purpose
- Responsibility
- Dependencies
- Related modules
- Processes
- Process codes
- Activities
- Permissions
- Routes
- Services
- APIs
- Database
- Important relationships

---

# 36. Changelog

Where Laravel Changelog is configured, maintain meaningful entries.

Preferred categories:

```text
feat
fix
refactor
security
performance
docs
breaking
```

Example:

```text
feat: Add Sales Booking receipt confirmation workflow
fix: Correct Pricing RTO rule validation
security: Restrict Sales Booking payment API authorization
```

Where practical, identify the module/process.

---

# 37. Git Branch Naming

Prefer module-identifiable branches.

Examples:

```text
feature/sales-booking-payment
feature/pricing-rto-rules
feature/inventory-vehicle-import
fix/sales-booking-receipt
refactor/pricing-rto-service
```

---

# 38. Commit Naming

Prefer:

```text
type(module): description
```

Examples:

```text
feat(sales-booking): add receive payment workflow
fix(sales-booking): validate receipt amount
feat(pricing-rto): add vehicle mapping
refactor(pricing-rto): extract rule calculation service
docs(sales-booking): add API documentation
```

---

# 39. Pull Request Traceability

Where applicable, PRs should identify:

```text
Module:
Process:
Activity:

Permission(s):
Routes:
Tables:
Models:
Services:
Actions:
APIs:
Views:
Jobs:
Tests:
Documentation:
```

This keeps business ownership traceable through review.

---

# 40. Testing

Changes should have appropriate tests.

Use the project's established testing framework:

```text
PHPUnit / Pest
```

Tests should cover, where applicable:

- Business behavior
- Validation
- Authorization
- Important edge cases
- Regression-sensitive behavior
- Service/Action behavior
- APIs
- Critical scope/RBAC logic

Passing syntax or code review is not equivalent to passing tests.

---

# 41. Static Analysis and Quality

Where configured, maintain:

```text
Laravel Pint
PHPStan / Larastan
SonarQube
PHPUnit / Pest
Swagger/OpenAPI
Laradocs
Laravel Changelog
```

Applicable feature changes should be checked against the configured quality tooling.

Do not claim a check passed unless it was actually performed.

---

# 42. Code Quality

Look for and prevent:

- Duplicate logic
- Dead code
- Debug statements
- Giant controllers
- God classes
- Hidden business logic in Blade
- Business workflows in Models
- Duplicate domain implementations
- Ambiguous ownership
- Unnecessary abstractions
- Unclear dependencies
- Excessive module coupling
- Inconsistent API contracts
- Missing authorization
- Missing validation
- Unnecessary global utilities

Prefer:

- Clear ownership
- Small focused methods
- Strong typing
- Explicit contracts
- Existing project utilities
- Testable business logic
- Consistent module organization

---

# 43. High-Risk Architectural Areas

Treat these areas as high-risk when auditing or modifying:

- RBAC services
- Scope-resolution services
- Organization hierarchy services
- Person/user normalization services
- Shared database utilities
- Shared caching utilities
- Authentication/authorization infrastructure
- High fan-out Services
- Cross-module service abstractions
- Central configuration
- Core shared utilities
- Highly coupled legacy/business-critical areas

Before reporting a proposed change as safe, inspect callers and dependencies.

---

# 44. Existing Architecture as Evidence

Before determining that something is architecturally wrong:

1. Inspect the repository structure.
2. Find the closest existing implementation.
3. Inspect callers.
4. Inspect dependencies.
5. Inspect Services/Actions.
6. Inspect Models.
7. Inspect permissions.
8. Inspect routes.
9. Inspect views.
10. Inspect APIs.
11. Inspect tests.
12. Inspect documentation where relevant.

The repository's actual established architecture must be considered before applying generic Laravel advice.

---

# 45. AI / Cline Development Rules

AI-generated code must follow these conventions.

Before creating code, Cline should:

1. Identify Module.
2. Identify Process.
3. Identify Activity.
4. Search for existing implementations.
5. Inspect relevant contracts.
6. Check permissions.
7. Check routes.
8. Check Services/Actions.
9. Check Models.
10. Check tests.
11. Reuse existing utilities where appropriate.
12. Avoid duplicate infrastructure.
13. Preserve established contracts.
14. Make the smallest reasonable change.

Do not generate generic standalone Laravel code when a suitable Xcelr8 implementation already exists.

---

# 46. AI Context Priorities

When interpreting project instructions, use this conceptual priority:

```text
1. Explicit current task requirements
2. Established Xcelr8 architectural contracts
3. .ai/conventions.md
4. Existing repository implementation patterns
5. Module-specific established patterns
6. Laravel/PHP conventions
7. Generic framework best practices
```

Security and functional correctness remain mandatory.

---

# 47. AI Refactoring Rules

Before proposing a refactor, establish:

```text
Why is the current implementation a problem?
What contract does it currently provide?
Who calls it?
What dependencies exist?
What would break if it moved?
Can the problem be fixed without moving it?
Is the refactor necessary for the current task?
```

Prefer targeted changes over unrelated architectural rewrites.

---

# 48. AI Dependency-Tracing Rule

Before changing a shared or central method, inspect where practical:

```text
Direct callers
Dependency injection bindings
Interfaces/contracts
Routes
Controllers
Jobs
Commands
Events
Listeners
Tests
Scheduled tasks
Imports
Exports
Console commands
API consumers
```

Do not assume a visible controller is the only consumer.

---

# 49. AI Security Rules

AI must never:

- Expose secrets.
- Put API keys into source code.
- Commit `.env`.
- Log credentials.
- Remove authorization merely for convenience.
- Weaken validation merely to simplify implementation.
- Disable security controls without explicit requirement and review.
- Introduce unsafe fallback authentication.
- Copy sensitive environment values into documentation.

Secrets belong in environment/configuration mechanisms appropriate to the project.

---

# 50. AI Database Rules

Before changing database-related code:

1. Inspect actual table usage.
2. Inspect model relationships.
3. Inspect foreign keys.
4. Inspect relevant indexes.
5. Inspect queries.
6. Inspect migrations if present.
7. Inspect schema documentation where available.
8. Do not invent schema assumptions.

Where migrations are incomplete or unavailable, inspect the real database/schema representation before making substantial schema-dependent changes.

---

# 51. AI RBAC / Scope Rules

RBAC and scope logic are high-risk.

Before changing authorization or scope behavior, inspect relevant:

```text
Users
Roles
Permissions
Role assignment
Permission checks
Middleware
Policies
OrgService
PersonService
Scope resolution
Caching
Authentication state
Module restrictions
Division restrictions
Designation restrictions
```

Do not replace established Xcelr8 authorization behavior with generic Laravel authorization patterns without understanding the existing contracts.

---

# 52. AI Reuse Rule

Before creating a new helper, utility, component or service, search for:

- Existing Service
- Existing Action
- Existing helper
- Existing trait
- Existing query scope
- Existing component
- Existing formatter
- Existing validation rule
- Existing authorization mechanism
- Existing API resource

Prefer reuse or extension over duplicate infrastructure.

---

# 53. AI Audit Mode

A repository audit is a **read-only analysis activity** unless the user explicitly authorizes remediation.

During an audit:

- Inspect the full application codebase.
- **Exclude `vendor/` from inspection and findings.**
- Do not report third-party/vendor code as Xcelr8 violations.
- Inspect project configuration and application code needed to establish architectural context.
- Trace dependencies before declaring a problem.
- Distinguish confirmed violations from uncertain observations.
- Do not modify application source code merely while auditing.
- Do not silently alter project standards during the audit.
- Do not invent missing architecture.

The purpose of an audit is to identify and document the current state accurately.

---

# 54. Audit Scope

Default code-audit scope:

```text
INCLUDE:
app/
bootstrap/
config/
database/
public/
resources/
routes/
tests/
project-level configuration
composer.json / composer.lock
package.json / package-lock.json / yarn.lock where relevant
documentation
custom scripts and project tooling

EXCLUDE:
vendor/
```

Additional directories should be included when they contain project-owned application code.

Do not automatically exclude a directory simply because it is unfamiliar.

---

# 55. Audit Finding Categories

Use categories such as:

```text
ARCHITECTURE
OWNERSHIP
NAMING
NAMESPACE
ROUTES
PERMISSIONS
RBAC
SCOPE
CONTROLLER
REQUEST
MODEL
SERVICE
ACTION
JOB
EVENT
VIEW
COMPONENT
DATABASE
API
SWAGGER
DOCUMENTATION
TESTING
QUALITY
SECURITY
PERFORMANCE
DUPLICATION
MAINTAINABILITY
CONFIGURATION
GIT
```

---

# 56. Audit Finding Severity

Use:

```text
CRITICAL
HIGH
MEDIUM
LOW
INFO
```

Prioritize:

```text
Security
Authorization
Data integrity
Functional correctness
Architecture
Maintainability
Consistency
Cosmetic issues
```

---

# 57. Audit Finding Status

Use:

```text
NEW
TECHNICAL-DEBT
POTENTIAL-RISK
HUMAN-REVIEW
```

The audit should not use historical/legacy classification to dismiss a violation.

A standards violation found during this audit should be reported even if it already exists throughout the repository.

---

# 58. Audit Evidence Rule

Do not report speculative findings as confirmed defects.

Before claiming that something is:

- unused
- duplicated
- unreachable
- incorrectly scoped
- incorrectly authorized
- safe to remove
- safe to move
- independent from a dependency

inspect sufficient references and dependencies.

Use evidence such as:

```text
File
Line(s)
Class
Method
Route
Permission
Caller
Reference
Configuration
Test
```

---

# 59. Audit Finding Format

Each finding should contain:

```text
ID
Severity
Category
Status
Module
Process
Activity
File
Line(s)

Current implementation

Rule violated

Why it matters

Evidence

Recommended solution

Implementation approach

Risk of remediation

Dependencies / impacted files

Suggested verification

Priority
```

Prefer precise file/line references.

---

# 60. Audit Solution Format

Solutions must be specific and point-wise.

Example:

```text
1. Move the class into the correct module/process namespace.
2. Preserve the current public contract.
3. Update namespace.
4. Update imports.
5. Update dependency-injection bindings.
6. Update callers.
7. Update relevant route references.
8. Update tests.
9. Run Pint.
10. Run PHPStan/Larastan.
11. Run affected tests.
```

Avoid vague recommendations such as:

```text
Refactor this for better architecture.
```

---

# 61. Architectural Hotspot Reporting

The audit should identify high-coupling or high-impact files/classes.

Examples:

```text
OrgService
PersonService
RBAC-related services
Scope-resolution utilities
Shared database utilities
Shared caching utilities
Central authentication components
High fan-out services
```

For each hotspot, identify:

- Why it is central.
- Major callers.
- Major dependencies.
- Areas requiring caution.

---

# 62. Audit Output Expectations

A complete repository audit should produce:

```text
1. Executive summary
2. Repository architecture summary
3. Module/process/activity inventory
4. Standards findings
5. Security/RBAC findings
6. Data/database findings
7. API findings
8. Quality/tooling findings
9. Architectural hotspots
10. Highest-priority remediation roadmap
11. Quick wins
12. Areas requiring human review
```

Findings must be evidence-based and actionable.

---

# 63. Golden Traceability

A complete business capability should ideally be traceable:

```text
MODULE
    ↓
PROCESS
    ↓
ACTIVITY
    ↓
PERMISSION
    ↓
ROUTE
    ↓
CONTROLLER
    ↓
FORM REQUEST
    ↓
SERVICE / ACTION
    ↓
MODEL
    ↓
TABLE
    ↓
VIEW
    ↓
API
    ↓
SWAGGER
    ↓
DOCUMENTATION
    ↓
TEST
    ↓
CHANGELOG
    ↓
GIT BRANCH / COMMIT
```

Missing or broken links in this chain are audit candidates.

---

# 64. Final AI Rules

When working on Xcelr8:

1. Understand before modifying.
2. Inspect before inventing.
3. Identify ownership before creating files.
4. Trace dependencies before moving/changing central code.
5. Reuse before duplicating.
6. Preserve existing contracts unless the task requires a contract change.
7. Keep Module → Process → Activity traceability.
8. Keep controllers thin.
9. Keep domain logic in Services/Actions.
10. Keep Models domain-oriented.
11. Keep permissions explicit.
12. Keep routes module-specific.
13. Keep namespaces aligned with physical paths.
14. Keep database ownership clear.
15. Keep APIs documented.
16. Keep tests aligned with changes.
17. Treat RBAC and scope infrastructure as high-risk.
18. Treat the entire repository as auditable except `vendor/`.
19. Report real violations even when they are widespread.
20. Never claim a verification was performed unless it was actually performed.

The goal is not merely to produce code that compiles.

The goal is to keep Xcelr8:

```text
Discoverable
Predictable
Modular
Traceable
Reusable
Testable
Maintainable
Secure
```

# END OF XCELR8 CONVENTIONS

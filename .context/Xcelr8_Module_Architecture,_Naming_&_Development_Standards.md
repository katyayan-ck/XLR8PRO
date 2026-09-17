# Xcelr8 Module Architecture, Naming & Development Standards

## 1. Purpose

Xcelr8 is developed by multiple teams working simultaneously on different business modules.

To prevent one team's code from becoming mixed with another team's code, every module must be independently identifiable and organized consistently across:

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
- Blade Views
- Components
- Database Tables
- Database Columns
- Notifications
- Documentation
- Swagger/OpenAPI definitions
- Tests

The objective is that a developer should be able to answer:

> "Which module owns this code, process, permission, route, table, view, API or service?"

simply by looking at its name and location.

This standard is mandatory for all new development and should be applied incrementally when existing modules are modified.

---

# 2. Module → Process → Activity Architecture

Xcelr8 uses a three-level functional hierarchy:

```text
Module
    └── Process
          └── Activity
```

For example:

```text
Sales
 ├── Booking
 │    ├── Create
 │    ├── Edit
 │    ├── View
 │    ├── Receive Payment
 │    ├── Confirm Receipt
 │    └── Cancel
 │
 ├── Enquiry
 │    ├── Create
 │    ├── Edit
 │    ├── View
 │    └── Convert
 │
 ├── Quotation
 │    ├── Create
 │    ├── Edit
 │    ├── View
 │    └── Approve
 │
 └── Billing
      ├── Create
      ├── Edit
      ├── View
      └── Cancel
```

Every functional capability must belong to a Module and Process.

An Activity represents the specific operation that a user is permitted to perform.

---

# 3. Module and Process Codes

Every Module must have a unique code.

Every Process must have a unique code within the project.

Example:

| Level | Name | Code |
|---|---|---|
| Module | Sales | `SLS` |
| Process | Booking | `BKNG` |
| Activity | Receive Payment | `RCPAY` |
| Activity | Create | `CREATE` |
| Activity | Edit | `EDIT` |
| Activity | View | `VIEW` |

Codes must:

- Be uppercase.
- Use only letters and numbers.
- Not contain spaces.
- Be stable once published.
- Not be changed merely because a developer prefers another abbreviation.
- Be registered in the project's Module/Process/Activity master.

---

# 4. Permission Naming Standard

The permission code is:

```text
MODULE_PROCESS_ACTIVITY
```

Example:

```text
SLS_BKNG_RCPAY
```

Meaning:

```text
SLS  = Sales
BKNG = Booking
RCPAY = Receive Payment
```

Therefore:

```text
SLS_BKNG_CREATE
SLS_BKNG_EDIT
SLS_BKNG_VIEW
SLS_BKNG_RCPAY
SLS_BKNG_CONFIRM_RECEIPT
SLS_BKNG_CANCEL
```

## 4.1 Permission code is the canonical identifier

The permission code must be treated as the permanent technical identifier.

Display names may change:

```text
Receive Payment
Receive Customer Payment
Payment Receipt
```

but the technical permission should remain:

```text
SLS_BKNG_RCPAY
```

unless there is an intentional permission migration.

## 4.2 Never use generic permissions

Avoid:

```text
create
edit
view
approve
delete
```

These are ambiguous in a large system.

Use:

```text
SLS_BKNG_CREATE
SLS_BKNG_EDIT
SLS_BKNG_VIEW
SLS_BKNG_APPROVE
```

---

# 5. Permission Naming and Route Naming Are Related but Different

Permissions identify **authorization**.

Routes identify **HTTP endpoints**.

They must not be treated as the same thing.

For example:

```text
Permission:
SLS_BKNG_RCPAY
```

may protect:

```text
POST /sales/booking/{booking}/receive-payment
```

with route name:

```text
sales.booking.receive-payment
```

The route name describes the technical endpoint.

The permission code describes the business authorization.

---

# 6. Route Naming Standard

Xcelr8 routes must follow Laravel route naming conventions.

Use:

```text
module.process.activity
```

Example:

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

For admin routes:

```text
admin.sales.booking.index
admin.sales.booking.create
admin.sales.booking.receive-payment
```

Route names must be:

- lowercase
- descriptive
- dot separated
- module identifiable
- process identifiable
- activity identifiable

---

# 7. Route URI Standard

Route URIs must use lowercase kebab-case.

Example:

```text
/sales/bookings
/sales/bookings/create
/sales/bookings/{booking}
/sales/bookings/{booking}/edit
/sales/bookings/{booking}/receive-payment
/sales/bookings/{booking}/confirm-receipt
```

Do not use:

```text
/Sales/Booking
/salesBooking
/sales_booking
/sales/Booking/Create
```

---

# 8. Standalone Route File Per Module

This is a mandatory Xcelr8 rule.

Each business module must have its own route file.

Do not place every module's routes into one huge:

```text
routes/web.php
```

file.

Instead:

```text
routes/
├── web.php
├── api.php
├── sales.php
├── pricing.php
├── inventory.php
├── billing.php
├── hr.php
└── reports.php
```

The module route file contains only routes belonging to that module.

---

# 9. Example: Sales Route File

Create:

```text
routes/sales.php
```

Example:

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Sales\Booking\BookingController;
use App\Http\Controllers\Sales\Booking\BookingPaymentController;
use App\Http\Controllers\Sales\Enquiry\EnquiryController;
use App\Http\Controllers\Sales\Quotation\QuotationController;

Route::prefix('sales')
    ->name('sales.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Booking
        |--------------------------------------------------------------------------
        */

        Route::prefix('bookings')
            ->name('booking.')
            ->group(function () {

                Route::get('/', [BookingController::class, 'index'])
                    ->name('index');

                Route::get('/create', [BookingController::class, 'create'])
                    ->name('create');

                Route::post('/', [BookingController::class, 'store'])
                    ->name('store');

                Route::get('/{booking}', [BookingController::class, 'show'])
                    ->name('show');

                Route::get('/{booking}/edit', [BookingController::class, 'edit'])
                    ->name('edit');

                Route::put('/{booking}', [BookingController::class, 'update'])
                    ->name('update');

                Route::post(
                    '/{booking}/receive-payment',
                    [BookingPaymentController::class, 'receivePayment']
                )->name('receive-payment');

                Route::post(
                    '/{booking}/confirm-receipt',
                    [BookingPaymentController::class, 'confirmReceipt']
                )->name('confirm-receipt');

                Route::delete('/{booking}', [BookingController::class, 'destroy'])
                    ->name('destroy');
            });


        /*
        |--------------------------------------------------------------------------
        | Enquiry
        |--------------------------------------------------------------------------
        */

        Route::prefix('enquiries')
            ->name('enquiry.')
            ->group(function () {

                Route::get('/', [EnquiryController::class, 'index'])
                    ->name('index');

                Route::get('/create', [EnquiryController::class, 'create'])
                    ->name('create');

                Route::post('/', [EnquiryController::class, 'store'])
                    ->name('store');

                Route::get('/{enquiry}', [EnquiryController::class, 'show'])
                    ->name('show');
            });


        /*
        |--------------------------------------------------------------------------
        | Quotation
        |--------------------------------------------------------------------------
        */

        Route::prefix('quotations')
            ->name('quotation.')
            ->group(function () {

                Route::get('/', [QuotationController::class, 'index'])
                    ->name('index');

                Route::get('/{quotation}', [QuotationController::class, 'show'])
                    ->name('show');

                Route::post(
                    '/{quotation}/approve',
                    [QuotationController::class, 'approve']
                )->name('approve');
            });
    });
```

The resulting route names are:

```text
sales.booking.index
sales.booking.create
sales.booking.store
sales.booking.show
sales.booking.edit
sales.booking.update
sales.booking.receive-payment
sales.booking.confirm-receipt
sales.booking.destroy

sales.enquiry.index
sales.enquiry.create
sales.enquiry.store
sales.enquiry.show

sales.quotation.index
sales.quotation.show
sales.quotation.approve
```

---

# 10. Loading Module Route Files

`routes/web.php` should act primarily as the central route loader.

Example:

```php
<?php

require base_path('routes/sales.php');
require base_path('routes/pricing.php');
require base_path('routes/inventory.php');
require base_path('routes/billing.php');
```

For larger projects, route registration can also be centralized through a dedicated provider.

The important rule is:

> `web.php` should not become the business module's route-definition file.

---

# 11. Route Permission Protection

Authorization should be attached to the route or handled through middleware/policies according to the project's authorization architecture.

Example:

```php
Route::post(
    '/{booking}/receive-payment',
    [BookingPaymentController::class, 'receivePayment']
)
    ->middleware('permission:SLS_BKNG_RCPAY')
    ->name('receive-payment');
```

This makes the relationship immediately visible:

```text
Route
sales.booking.receive-payment

Permission
SLS_BKNG_RCPAY

Controller
Sales\Booking\BookingPaymentController

Method
receivePayment()
```

---

# 12. Controller Organization

Controllers must follow:

```text
App\Http\Controllers\
    Module\
        Process\
            Controller
```

Example:

```text
app/Http/Controllers/
└── Sales/
    ├── Booking/
    │   ├── BookingController.php
    │   └── BookingPaymentController.php
    │
    ├── Enquiry/
    │   └── EnquiryController.php
    │
    └── Quotation/
        └── QuotationController.php
```

Namespace:

```php
namespace App\Http\Controllers\Sales\Booking;
```

Controller:

```php
class BookingController extends Controller
{
    //
}
```

---

# 13. API Controllers

API controllers must follow the same hierarchy.

Example:

```text
app/Http/Controllers/Api/
└── Sales/
    ├── Booking/
    │   ├── BookingController.php
    │   └── BookingPaymentController.php
    │
    ├── Enquiry/
    │   └── EnquiryController.php
    │
    └── Quotation/
        └── QuotationController.php
```

Namespace:

```php
namespace App\Http\Controllers\Api\Sales\Booking;
```

API URLs:

```text
/api/v1/sales/bookings
/api/v1/sales/bookings/{booking}
/api/v1/sales/bookings/{booking}/receive-payment
```

Route names:

```text
api.v1.sales.booking.index
api.v1.sales.booking.show
api.v1.sales.booking.receive-payment
```

---

# 14. Models

Models must be grouped by module and process where the domain warrants it.

Example:

```text
app/Models/
└── Sales/
    ├── Booking/
    │   ├── Booking.php
    │   ├── BookingPayment.php
    │   └── BookingReceipt.php
    │
    ├── Enquiry/
    │   └── Enquiry.php
    │
    └── Quotation/
        └── Quotation.php
```

Namespaces:

```php
namespace App\Models\Sales\Booking;
```

Example:

```php
class Booking extends BaseModel
{
    //
}
```

The model class remains domain-oriented.

Do not use:

```text
SalesBookingModel
BookingModel
tblSalesBooking
```

---

# 15. Services

Services must follow the same module/process hierarchy.

```text
app/Services/
└── Sales/
    ├── Booking/
    │   ├── BookingService.php
    │   ├── BookingPaymentService.php
    │   └── BookingReceiptService.php
    │
    ├── Enquiry/
    │   └── EnquiryService.php
    │
    └── Quotation/
        └── QuotationService.php
```

Namespace:

```php
namespace App\Services\Sales\Booking;
```

Example:

```php
class BookingPaymentService
{
    public function receivePayment(...)
    {
        //
    }
}
```

---

# 16. Actions

When a service contains many independent use cases, use dedicated Actions.

Example:

```text
app/Actions/
└── Sales/
    └── Booking/
        ├── CreateBookingAction.php
        ├── UpdateBookingAction.php
        ├── ReceiveBookingPaymentAction.php
        ├── ConfirmBookingReceiptAction.php
        └── CancelBookingAction.php
```

This provides a direct mapping:

```text
SLS_BKNG_RCPAY
        ↓
ReceiveBookingPaymentAction
```

---

# 17. Jobs

Jobs must also be module-aware.

```text
app/Jobs/
└── Sales/
    └── Booking/
        ├── GenerateBookingInvoiceJob.php
        ├── SendBookingConfirmationJob.php
        └── SendPaymentReceiptJob.php
```

Example namespace:

```php
namespace App\Jobs\Sales\Booking;
```

---

# 18. Requests

Form Requests should follow the module/process structure.

```text
app/Http/Requests/
└── Sales/
    ├── Booking/
    │   ├── StoreBookingRequest.php
    │   ├── UpdateBookingRequest.php
    │   └── ReceiveBookingPaymentRequest.php
    │
    ├── Enquiry/
    │   └── StoreEnquiryRequest.php
    │
    └── Quotation/
        └── ApproveQuotationRequest.php
```

---

# 19. Imports and Exports

Imports and exports must never be dumped into one common directory.

Example:

```text
app/Imports/
└── Sales/
    ├── Booking/
    │   └── BookingImport.php
    └── Enquiry/
        └── EnquiryImport.php
```

Exports:

```text
app/Exports/
└── Sales/
    ├── Booking/
    │   └── BookingExport.php
    └── Quotation/
        └── QuotationExport.php
```

---

# 20. Blade View Structure

Blade views must mirror:

```text
Module
    └── Process
          └── Activity
```

Example:

```text
resources/views/
└── Sales/
    ├── Booking/
    │   ├── Create/
    │   │   └── index.blade.php
    │   ├── Edit/
    │   │   └── index.blade.php
    │   ├── View/
    │   │   └── index.blade.php
    │   ├── List/
    │   │   └── index.blade.php
    │   ├── ReceivePayment/
    │   │   └── index.blade.php
    │   └── ConfirmReceipt/
    │       └── index.blade.php
    │
    ├── Enquiry/
    │   ├── Create/
    │   ├── Edit/
    │   ├── View/
    │   └── List/
    │
    └── Quotation/
        ├── Create/
        ├── Edit/
        ├── View/
        ├── List/
        └── Approve/
```

Blade naming should still follow the project's Laravel convention of kebab-case for individual files.

For example:

```text
resources/views/sales/booking/receive-payment/index.blade.php
```

is preferable to:

```text
resources/views/Sales.booking.Create.blade.php
```

The important architectural rule is the hierarchy, not whether dot notation is used in the physical directory name.

---

# 21. View Naming Convention

Recommended:

```text
resources/views/
    sales/
        booking/
            create/
                index.blade.php
            edit/
                index.blade.php
            list/
                index.blade.php
            view/
                index.blade.php
            receive-payment/
                index.blade.php
```

Usage:

```php
return view('sales.booking.receive-payment.index');
```

This gives the same functional identity:

```text
Sales → Booking → Receive Payment
```

while remaining compatible with standard Laravel view resolution.

---

# 22. View Components

Module-specific components should remain inside the module.

Example:

```text
resources/views/components/sales/booking/
    booking-status.blade.php
    booking-summary.blade.php
    payment-summary.blade.php
```

If a component is genuinely reusable across the entire application, it may be placed under:

```text
resources/views/components/
```

Do not put module-specific components into the global component directory.

---

# 23. Database Table Naming

Database tables must carry the Xcelr8 and module identity where project architecture requires module isolation.

Format:

```text
xcelr8_{module}_{entity}
```

For processes containing multiple related entities:

```text
xcelr8_{module}_{process}_{entity}
```

Example:

```text
xcelr8_pricing_rto_rules
xcelr8_pricing_rto_values
xcelr8_pricing_rto_vehicle_mapping
```

For Sales:

```text
xcelr8_sales_bookings
xcelr8_sales_booking_payments
xcelr8_sales_booking_receipts
xcelr8_sales_enquiries
xcelr8_sales_quotations
xcelr8_sales_bills
```

---

# 24. Table Naming Rules

All table names must:

- be lowercase
- use snake_case
- use plural nouns where appropriate
- contain the module identifier
- contain the process identifier when required
- avoid ambiguous abbreviations
- never use spaces
- never use PascalCase
- never use camelCase

Bad:

```text
SalesBooking
salesBooking
tbl_sales_booking
bookingData
```

Good:

```text
xcelr8_sales_bookings
xcelr8_sales_booking_payments
```

---

# 25. Model → Table Mapping

Where the table name follows Laravel conventions, Eloquent should be allowed to infer it.

If the Xcelr8 table name does not match the conventional Eloquent table name, explicitly define `$table`.

Example:

```php
class RtoRule extends BaseModel
{
    protected $table = 'xcelr8_pricing_rto_rules';
}
```

The model name must remain domain-oriented:

```text
RtoRule
```

not:

```text
Xcelr8PricingRtoRulesModel
```

---

# 26. Foreign Keys

Foreign keys should remain descriptive.

Example:

```text
booking_id
customer_id
vehicle_id
rto_rule_id
```

Do not use:

```text
bkng_id
cust_id
veh_id
```

unless the abbreviation is an intentional database-standard exception.

The table prefix does not change the foreign-key convention.

---

# 27. Migrations

Migration filenames must describe the schema operation.

Example:

```text
create_xcelr8_pricing_rto_rules_table
create_xcelr8_sales_bookings_table
add_status_to_xcelr8_sales_bookings_table
add_approved_at_to_xcelr8_sales_quotations_table
```

Migrations should be grouped logically by module in source-control review even though Laravel executes them chronologically.

---

# 28. Module Directory Standard

Where practical, the application should follow this conceptual structure:

```text
app/
├── Actions/
│   └── Sales/
├── Exports/
│   └── Sales/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   └── Sales/
│   │   └── Sales/
│   ├── Requests/
│   │   └── Sales/
│   └── Resources/
│       └── Sales/
├── Imports/
│   └── Sales/
├── Jobs/
│   └── Sales/
├── Models/
│   └── Sales/
├── Services/
│   └── Sales/
└── ...

resources/
├── views/
│   └── sales/
│       └── booking/
│           ├── create/
│           ├── edit/
│           ├── list/
│           ├── view/
│           └── receive-payment/
└── docs/
    └── sales/

routes/
├── web.php
├── api.php
├── sales.php
├── pricing.php
└── ...
```

---

# 29. Namespace Rule

Namespace must follow the physical directory structure.

Example:

```text
app/Services/Sales/Booking/BookingPaymentService.php
```

must use:

```php
namespace App\Services\Sales\Booking;
```

Similarly:

```text
app/Models/Sales/Booking/Booking.php
```

uses:

```php
namespace App\Models\Sales\Booking;
```

and:

```text
app/Http/Controllers/Api/Sales/Booking/BookingController.php
```

uses:

```php
namespace App\Http\Controllers\Api\Sales\Booking;
```

Never place a class in one module directory and give it another module's namespace.

---

# 30. Module Ownership Rule

Every artifact must have one clear owning module.

For example:

```text
RtoRule
```

belongs to:

```text
Pricing
```

and therefore should not be stored under:

```text
Sales/
Inventory/
Vehicle/
```

unless it is genuinely an integration object belonging to those modules.

If multiple modules consume a service, the service should normally remain owned by its original domain module or be promoted to a clearly defined shared/core service.

Do not duplicate the same business logic in multiple modules.

---

# 31. Shared/Core Code

Not everything belongs to a business module.

Common functionality should live in clearly defined shared areas:

```text
app/
├── Core/
├── Shared/
├── Support/
└── Services/
```

Examples:

```text
NotificationService
FileStorageService
AuditService
EncryptionService
PdfService
```

A shared service must not become a dumping ground.

Before placing code into `Shared`, determine:

> Is this genuinely shared infrastructure, or does it actually belong to one business module?

---

# 32. Backpack 7 Organization

Backpack CRUD controllers must follow the module hierarchy.

Example:

```text
app/Http/Controllers/Admin/
└── Sales/
    ├── Booking/
    │   └── BookingCrudController.php
    ├── Enquiry/
    │   └── EnquiryCrudController.php
    └── Quotation/
        └── QuotationCrudController.php
```

Namespace:

```php
namespace App\Http\Controllers\Admin\Sales\Booking;
```

Example:

```php
class BookingCrudController extends CrudController
{
    //
}
```

Backpack routes should also remain module-specific.

---

# 33. API Documentation and Swagger/OpenAPI

Every API endpoint must be documented.

Documentation must identify:

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
Request
Authentication
Authorization
Parameters
Request Body
Response
Error Responses
```

Example:

```text
Module: Sales
Process: Booking
Activity: Receive Payment

Permission:
SLS_BKNG_RCPAY

Method:
POST

URI:
/api/v1/sales/bookings/{booking}/receive-payment

Route:
api.v1.sales.booking.receive-payment

Controller:
Sales\Booking\BookingPaymentController

Method:
receivePayment
```

---

# 34. Swagger/OpenAPI Comment Standard

Every API method must have complete OpenAPI documentation.

Example using PHP attributes:

```php
#[OA\Post(
    path: '/api/v1/sales/bookings/{booking}/receive-payment',
    operationId: 'salesBookingReceivePayment',
    summary: 'Receive payment for a booking',
    description: 'Records a payment against an existing sales booking.',
    tags: ['Sales - Booking'],
    security: [['bearerAuth' => []]],
)]
```

Parameters must be documented:

```php
#[OA\Parameter(
    name: 'booking',
    description: 'Booking ID',
    required: true,
    in: 'path',
    schema: new OA\Schema(type: 'integer')
)]
```

Request body:

```php
#[OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
        required: ['amount', 'payment_method'],
        properties: [
            new OA\Property(
                property: 'amount',
                type: 'number',
                format: 'float',
                example: 25000
            ),
            new OA\Property(
                property: 'payment_method',
                type: 'string',
                example: 'UPI'
            ),
        ]
    )
)]
```

Responses:

```php
#[OA\Response(
    response: 200,
    description: 'Payment received successfully'
)]
```

and documented errors:

```php
#[OA\Response(
    response: 401,
    description: 'Unauthenticated'
)]

#[OA\Response(
    response: 403,
    description: 'Permission denied'
)]

#[OA\Response(
    response: 404,
    description: 'Booking not found'
)]

#[OA\Response(
    response: 422,
    description: 'Validation error'
)]
```

---

# 35. Swagger Documentation Rules

Swagger documentation must not merely document the happy path.

Each API should document:

- Authentication requirements
- Required permission
- Path parameters
- Query parameters
- Request headers
- Request body
- Required fields
- Optional fields
- Data types
- Examples
- Success response
- Validation errors
- Authentication errors
- Authorization errors
- Not-found errors
- Business-rule errors where applicable

The documentation should allow another developer to consume the API without reading the controller source code.

---

# 36. API Response Standard

All APIs should use the project's standard response structure.

For example:

```json
{
    "success": true,
    "message": "Payment received successfully.",
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

The exact response contract must be standardized globally and not reinvented by individual modules.

---

# 37. Module Documentation with Laradocs

The project should maintain developer documentation for every significant module.

Install:

```bash
composer require petebishwhip/laradocs --dev
php artisan laradocs:install
```

Documentation should be stored under:

```text
resources/docs/
```

Recommended structure:

```text
resources/docs/
├── _index.md
├── architecture/
├── modules/
│   ├── sales/
│   │   ├── _index.md
│   │   ├── booking/
│   │   │   ├── _index.md
│   │   │   ├── overview.md
│   │   │   ├── permissions.md
│   │   │   ├── routes.md
│   │   │   ├── services.md
│   │   │   ├── api.md
│   │   │   └── database.md
│   │   └── quotation/
│   └── pricing/
│       ├── _index.md
│       └── rto-rules.md
└── development/
```

---

# 38. Module Documentation Minimum Requirements

Every major module should document:

## Module Overview

```text
Purpose
Business responsibility
Module owner
Dependencies
Related modules
```

## Processes

```text
Process
Code
Purpose
Activities
```

## Permissions

```text
Permission Code
Permission Name
Process
Activity
Description
```

## Routes

```text
HTTP Method
URI
Route Name
Permission
Controller
Action
```

## Services

```text
Service
Purpose
Public methods
Parameters
Return value
Dependencies
```

## APIs

```text
Endpoint
Authentication
Permission
Request
Response
Errors
Examples
```

## Database

```text
Tables
Purpose
Important columns
Relationships
Indexes
```

---

# 39. Example Laradocs Front Matter

Example:

```markdown
---
title: Sales Booking
description: Sales Booking module technical documentation
order: 1
group: Sales
---

# Sales Booking

The Sales Booking process manages customer vehicle bookings.

## Process Code

`BKNG`

## Module

`SLS`
```

---

# 40. Changelog Standard

Install:

```bash
composer require markwalet/laravel-changelog --dev
php artisan changelog:install
```

The package creates:

```text
.changes/
```

Each feature branch should maintain its own change entry.

Example:

```bash
php artisan changelog:add \
    --type=feat \
    --message="Added Sales Booking payment receipt workflow"
```

Review pending changes:

```bash
php artisan changelog:unreleased
```

Release:

```bash
php artisan changelog:release
```

This avoids multiple developers simultaneously editing the same central `CHANGELOG.md`.

---

# 41. Changelog Message Standard

Changelog types should describe the nature of the change.

Examples:

```text
feat
fix
refactor
security
performance
docs
breaking
```

Good:

```text
Added Sales Booking payment receipt workflow
Added RTO rule vehicle mapping
Fixed quotation approval authorization
Improved booking search performance
```

Bad:

```text
changes
updated code
bug fixed
minor update
work done
```

Whenever possible, include the module/process.

Example:

```text
feat: Added Sales Booking receipt confirmation workflow
fix: Fixed Pricing RTO rule validation
security: Restricted Sales Booking payment API authorization
```

---

# 42. Git Branch Naming

Branches should also identify the module.

Recommended:

```text
feature/sales-booking-payment
feature/pricing-rto-rules
feature/inventory-vehicle-import
fix/sales-booking-receipt
refactor/pricing-rto-service
```

This makes ownership immediately visible during parallel development.

---

# 43. Commit Message Standard

Commit messages should identify the work performed.

Recommended:

```text
feat(sales-booking): add receive payment workflow
fix(sales-booking): validate receipt amount
feat(pricing-rto): add vehicle mapping
refactor(pricing-rto): extract rule calculation service
docs(sales-booking): add API documentation
```

---

# 44. Pull Request Standard

Every pull request should clearly state:

```text
Module:
Process:
Activity:

Permission(s):

Routes:

Tables:

Models:

Services:

APIs:

Views:

Jobs:

Tests:
```

Example:

```text
Module: Sales
Process: Booking
Activity: Receive Payment

Permissions:
SLS_BKNG_RCPAY
SLS_BKNG_CONFIRM_RECEIPT

Routes:
sales.booking.receive-payment
sales.booking.confirm-receipt

Tables:
xcelr8_sales_booking_payments
xcelr8_sales_booking_receipts
```

---

# 45. Module Development Checklist

Before submitting a module feature, verify:

### Architecture

- [ ] Correct Module identified.
- [ ] Correct Process identified.
- [ ] Correct Activity identified.
- [ ] Permission code created.
- [ ] Permission code follows `MODULE_PROCESS_ACTIVITY`.

### Routes

- [ ] Routes are in the module-specific route file.
- [ ] URI uses kebab-case.
- [ ] Route name uses dot notation.
- [ ] Route name identifies module/process/activity.
- [ ] Authorization is applied.

### Controllers

- [ ] Controller is in the correct module namespace.
- [ ] Controller is thin.
- [ ] No complex business logic.
- [ ] Form Request used where appropriate.

### Models

- [ ] Model is in correct module namespace.
- [ ] Model uses domain naming.
- [ ] Relationships are defined.
- [ ] No unnecessary business workflow inside model.

### Services/Actions

- [ ] Complex business logic moved out of controller.
- [ ] Service/Action belongs to correct module/process.
- [ ] Duplicate logic has not been introduced.

### Views

- [ ] Views are inside module/process directories.
- [ ] Activity is identifiable from the path.
- [ ] Blade files follow kebab-case.

### Database

- [ ] Table belongs to the correct module.
- [ ] Table uses `xcelr8_` prefix where required.
- [ ] Snake_case is used.
- [ ] Foreign keys follow standard naming.
- [ ] Indexes and constraints are appropriate.

### APIs

- [ ] API version is defined.
- [ ] Swagger/OpenAPI documentation exists.
- [ ] Request parameters are documented.
- [ ] Request body is documented.
- [ ] Success response is documented.
- [ ] Error responses are documented.
- [ ] Permission is documented.

### Documentation

- [ ] Laradocs documentation updated.
- [ ] Module/process documentation updated.
- [ ] Changelog entry created.

### Quality

- [ ] Tests added/updated.
- [ ] Laravel Pint passes.
- [ ] PHPStan/Larastan passes.
- [ ] SonarQube checks pass.
- [ ] No debug code.
- [ ] No dead code.
- [ ] No unrelated changes.

---

# 46. Complete Example: Sales → Booking → Receive Payment

A developer implementing:

```text
Sales
 └── Booking
      └── Receive Payment
```

should produce a structure similar to:

```text
Permission
SLS_BKNG_RCPAY
```

```text
Route
sales.booking.receive-payment
```

```text
URI
POST /sales/bookings/{booking}/receive-payment
```

```text
Controller
App\Http\Controllers\Sales\Booking\BookingPaymentController
```

```text
Method
receivePayment()
```

```text
Request
App\Http\Requests\Sales\Booking\ReceiveBookingPaymentRequest
```

```text
Service
App\Services\Sales\Booking\BookingPaymentService
```

```text
Action
App\Actions\Sales\Booking\ReceiveBookingPaymentAction
```

```text
Model
App\Models\Sales\Booking\BookingPayment
```

```text
Table
xcelr8_sales_booking_payments
```

```text
View
resources/views/sales/booking/receive-payment/index.blade.php
```

```text
Job
App\Jobs\Sales\Booking\SendPaymentReceiptJob
```

```text
API
POST /api/v1/sales/bookings/{booking}/receive-payment
```

```text
Swagger Tag
Sales - Booking
```

```text
Documentation
resources/docs/modules/sales/booking/receive-payment.md
```

```text
Git Branch
feature/sales-booking-payment
```

```text
Commit
feat(sales-booking): add receive payment workflow
```

This creates a complete technical trace:

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
REQUEST
  ↓
SERVICE/ACTION
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
CHANGELOG
```

---

# 47. Golden Rule

Every developer should be able to determine the ownership of a piece of code without opening unrelated files.

If a developer sees:

```text
SLS_BKNG_RCPAY
```

they should immediately understand:

```text
Sales → Booking → Receive Payment
```

If they see:

```text
sales.booking.receive-payment
```

they should understand the same business location.

If they see:

```text
App\Services\Sales\Booking\BookingPaymentService
```

they should understand the same module/process.

If they see:

```text
xcelr8_sales_booking_payments
```

they should understand the same module/process.

If they see:

```text
resources/views/sales/booking/receive-payment/
```

they should understand the same module/process/activity.

This consistency is the primary architectural objective.

---

# 48. Existing Laravel Standards Remain Applicable

The module standard does not replace Laravel conventions.

The following existing Xcelr8 rules remain mandatory:

- PascalCase PHP classes.
- camelCase methods and variables.
- snake_case database columns.
- kebab-case route URIs.
- dot notation route names.
- thin controllers.
- Form Requests for non-trivial validation.
- domain-oriented Models.
- dedicated Services/Actions for complex business logic.
- clean code.
- tests.
- static analysis.
- Laravel Pint.
- PHPStan/Larastan.
- SonarQube.

These principles are already established in the Xcelr8 coding standard. 
The module hierarchy is therefore an **Xcelr8 project-specific organizational layer on top of normal Laravel conventions**, not a replacement for Laravel conventions.

---

# 49. Enforcement

These rules are mandatory for new modules and new features.

For legacy modules:

> Do not perform risky bulk refactoring merely to satisfy the standard.

However, when modifying legacy code:

1. New code must follow the standard.
2. The touched area should be moved closer to the standard where practical.
3. Unrelated legacy code should not be refactored without justification.
4. Exceptions must be documented in the pull request.

The project review process should reject new code that introduces unnecessary module mixing.

---

# 50. Required Development Tools

The Xcelr8 development quality stack should include:

```text
Laravel Pint
PHPStan / Larastan
SonarQube
PHPUnit / Pest
Laravel Changelog
Laradocs
Swagger/OpenAPI
```

The existing coding standard already establishes formatter, static analysis, SonarQube and automated testing as the project quality stack.

No feature branch should be merged unless the applicable tests, formatter, static analysis and SonarQube quality requirements pass or an approved exception exists.
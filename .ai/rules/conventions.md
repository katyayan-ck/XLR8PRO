---
description: Naming conventions, PSR-12, module structure, API standards. Load when creating new files, refactoring, or setting code style.
paths:
  - app/**
  - routes/**
  - resources/**
---

# XCELR8 Code Conventions & Module Standards

## 1. PHP Standards

```php
<?php

declare(strict_types=1); // MANDATORY on every PHP file

namespace App\Services\Sales\Booking;

// Explicit type-hints on ALL: parameters, return types, class properties
class BookingPaymentService
{
    public function receivePayment(int $bookingId, float $amount, string $method): BookingPayment
    {
        // ...
    }
}
```

- **PSR-12** strictly enforced (Laravel Pint handles formatting)
- `declare(strict_types=1)` at top of EVERY PHP file
- Explicit type-hints on ALL function parameters, return types, and class properties

---

## 2. Naming Quick Reference

| Artifact | Convention | Example |
|---|---|---|
| PHP classes | PascalCase | `BookingPaymentService`, `VehicleVariant` |
| Methods / variables | camelCase | `receivePayment()`, `$bookingId` |
| DB tables | snake_case plural + module prefix | `xcelr8_sales_bookings` |
| DB columns | snake_case | `booking_status`, `created_by`, `person_code` |
| Route names | dot.notation.kebab | `sales.booking.receive-payment` |
| Route URIs | kebab-case | `/sales/bookings/{booking}/receive-payment` |
| Permissions | `MODULE_PROCESS_ACTIVITY` UPPER | `SLS_BKNG_RCPAY` |
| Module codes | UPPERCASE, letters+numbers only | `SLS`, `PRC`, `INV`, `HR` |
| Process codes | UPPERCASE, letters+numbers only | `BKNG`, `ENQR`, `QUOT` |
| Activity codes | UPPERCASE, letters+numbers only | `CREATE`, `EDIT`, `RCPAY` |
| Env vars | SCREAMING_SNAKE_CASE | `XCELR8_GATEWAY_BASE_URL` |
| Config keys | snake_case | `config('services.firebase.project_id')` |
| Blade views | kebab-case directories | `sales/booking/receive-payment/index.blade.php` |
| Branch names | `feature/{module}-{process}-{activity}` | `feature/sales-booking-payment` |
| Commit style | `type(scope): message` | `feat(sales-booking): add receive payment workflow` |

---

## 3. Module → Process → Activity Hierarchy

Every feature belongs to one Module and Process:

```
Module (e.g. Sales = SLS)
 └── Process (e.g. Booking = BKNG)
      └── Activity (e.g. Receive Payment = RCPAY)
           └── Permission: SLS_BKNG_RCPAY
```

**Codes must be:**
- Uppercase only
- Letters and numbers only, no spaces
- Stable once published — never change without a formal migration
- Registered in the project's Module/Process/Activity master

---

## 4. Full Example: Sales → Booking → Receive Payment

```
Permission:   SLS_BKNG_RCPAY
Route name:   sales.booking.receive-payment
URI:          POST /sales/bookings/{booking}/receive-payment
Controller:   App\Http\Controllers\Sales\Booking\BookingPaymentController@receivePayment
Request:      App\Http\Requests\Sales\Booking\ReceiveBookingPaymentRequest
Service:      App\Services\Sales\Booking\BookingPaymentService@receivePayment
Action:       App\Actions\Sales\Booking\ReceiveBookingPaymentAction
Model:        App\Models\Sales\Booking\BookingPayment
Table:        xcelr8_sales_booking_payments
View:         resources/views/sales/booking/receive-payment/index.blade.php
Job:          App\Jobs\Sales\Booking\SendPaymentReceiptJob
API:          POST /api/v1/sales/bookings/{booking}/receive-payment
Swagger tag:  Sales - Booking
Docs:         resources/docs/modules/sales/booking/receive-payment.md
Branch:       feature/sales-booking-payment
Commit:       feat(sales-booking): add receive payment workflow
```

---

## 5. Route File Structure

One route file per module (mandatory):

```php
// routes/web.php — loader only
require base_path('routes/sales.php');
require base_path('routes/pricing.php');
require base_path('routes/inventory.php');

// routes/sales.php — all sales routes only
Route::prefix('sales')->name('sales.')->group(function () {
    Route::prefix('bookings')->name('booking.')->group(function () {
        Route::get('/', [BookingController::class, 'index'])->name('index');
        Route::get('/create', [BookingController::class, 'create'])->name('create');
        Route::post('/', [BookingController::class, 'store'])->name('store');
        Route::post('/{booking}/receive-payment', [BookingPaymentController::class, 'receivePayment'])
            ->middleware('permission:SLS_BKNG_RCPAY')
            ->name('receive-payment');
    });
});
```

---

## 6. Controller Rules

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales\Booking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\Booking\ReceiveBookingPaymentRequest;
use App\Services\Sales\Booking\BookingPaymentService;

class BookingPaymentController extends Controller
{
    public function __construct(
        private readonly BookingPaymentService $bookingPaymentService
    ) {}

    public function receivePayment(ReceiveBookingPaymentRequest $request, int $bookingId): JsonResponse
    {
        // ✅ Thin: validate via FormRequest → call service → return response
        $payment = $this->bookingPaymentService->receivePayment($bookingId, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payment received successfully.',
            'data'    => $payment,
        ]);
    }
}
```

**Never in controllers:**
- Inline SQL or raw DB queries
- Complex business algorithms
- Multiple model operations without a service
- `$request->validate()` — use FormRequest instead

---

## 7. API Response Standard (ALL APIs)

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
    "errors": {
        "amount": ["The amount field is required."]
    }
}
```

HTTP status codes:
- `200` Success
- `201` Created
- `422` Validation error
- `401` Unauthenticated
- `403` Permission denied
- `404` Not found
- `500` Server error

---

## 8. API Route Standards

```php
// routes/api.php
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::prefix('sales/bookings')->name('sales.booking.')->group(function () {
        Route::get('/', [Api\V1\Sales\Booking\BookingController::class, 'index'])->name('index');
        Route::post('/{booking}/receive-payment', ...)
            ->name('receive-payment');
    });
});
```

API URL pattern: `/api/v1/{module}/{process}/{action}`

---

## 9. Backpack CRUD Controller

```php
namespace App\Http\Controllers\Admin\Sales\Booking;

use Backpack\CRUD\app\Http\Controllers\CrudController;

class BookingCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(\App\Models\Sales\Booking\Booking::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/sales/booking');
        CRUD::setEntityNameStrings('booking', 'bookings');
        $this->middleware('permission:SLS_BKNG_VIEW');
    }
}
```

---

## 10. Model Ownership Rule

Every artifact has ONE owning module. Cross-module consumption:
- If multiple modules consume a service → service stays in original domain or promotes to `App\Core` / `App\Shared`
- **Never duplicate business logic across modules**
- Shared/Core code: `NotificationService`, `FileStorageService`, `AuditService`, `PdfService`
- Before placing in Shared: ask "is this genuinely shared infrastructure, or does it belong to one module?"

---

## 11. Quality Gates (Required Before PR Merge)

```bash
./vendor/bin/pint                          # Laravel Pint formatting
./vendor/bin/phpstan analyse              # PHPStan / Larastan
php artisan test                           # PHPUnit / Pest
php artisan changelog:add --type=feat     # After each feature
php artisan laradocs:make                  # Update module docs
```

SonarQube quality gates must also pass.

---

## 12. Post-Sprint Commands to Offer

After completing any sprint, feature, or significant task, offer:

```bash
# Changelog entry
php artisan changelog:add --type=feat --message="Added {Module} {Process} {Activity} workflow"

# Module documentation
php artisan laradocs:make

# View pending changes
php artisan changelog:unreleased

# Release
php artisan changelog:release
```

---

## 13. Frontend / UI-UX Standards (recorded 23-09-2026, per explicit user instruction)

Applies whenever a Blade view, its CSS, or its JS is touched — not just new views.

- **Design system**: minimalistic, high-data-density layout in the Tabler admin theme style.
  Prefer free/OSS jQuery-ecosystem libraries already available in this stack over introducing new
  paid or heavyweight dependencies; get approval before adding a new frontend package
  (`package.json` changes require approval per the root CLAUDE.md dependency rule).
- **Uniform components**: cards, page shells, and AG-Grid tables must use one shared style across
  every module — don't hand-roll a one-off card/grid style per screen. When a screen's markup
  diverges from the established shared pattern, converge it during that screen's own change, don't
  leave it as accumulating drift.
- **Labels & validation messages — single source of truth**: every field's label and validation
  message must be defined once, in a centralized, per-project location (Laravel lang files under
  `resources/lang/`, wired for future multi-language support), and referenced from every
  form/screen/import/export that uses that field — never re-typed per view. This mirrors the
  Identifier & Reference Registry pattern already established for value formats
  (`App\Rules\*`/`IdentifierService`) — labels and messages get the same one-place treatment.
- **Date format**: all frontend date display and date-picker input must use `dd-MMM-YYYY`
  (e.g. `23-Sep-2026`) by default, sourced from a site-settings-backed config value (not
  hardcoded per view) so it can be changed project-wide from one place.
- **Performance**: apply query caching and other standard optimizations (eager loading, pagination,
  avoiding N+1 — see the Booking-listing N+1 fix precedent in `docs/refactor/ai-changelogs-*.md`)
  when touching a data-rendering screen, not just backend service code.
- **Before shipping any UI/UX change**: re-verify the screen's existing validation, AJAX calls, and
  JS logic still function correctly — a visual/style pass must not silently change or break
  functional behavior. Treat this the same as the backend "preserve current functioning" rule.

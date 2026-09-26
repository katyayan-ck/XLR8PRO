---
name: xcelr8-new-module
description: "Use when adding a new module or process, scaffolding CRUD, or creating a controller/service/route file/model that must follow the Xceler8 Module/Process/Activity structure, permission naming and menu gating. Triggers: new module, new process, scaffold, add CRUD, new feature, new controller, new service, new route file."
---

> Ported 26-09-2026 from `.ai/_archive/2026-09-26/.ai/skills/xcelr8-new-module` (DEC-031). Current facts in
> `.ai/rules/**` win over anything below that conflicts (e.g. dead code removed on 26-09-2026,
> roles = designations, tests on `xlrm_testing`, migrations not SQL-first).

# Skill: XCELR8 New Module Scaffold

**When to activate:** Any task that asks to create a new module, add a new process to an existing module, scaffold new CRUD, or add a new feature following Xcelr8 architecture.

**Keyword triggers:** new module, new process, scaffold, create module, add CRUD, new feature, new controller, new service, new route file, new model, start a new module.

---

## Pre-Flight Checklist

Before writing any code, confirm with the user:

1. **Module name and code** — e.g. "Sales" / `SLS`
2. **Process name and code** — e.g. "Booking" / `BKNG`
3. **Activities** — e.g. Create, Edit, View, Cancel, Receive Payment
4. **Permissions needed** — derived as `MODULE_PROCESS_ACTIVITY`
5. **DB tables needed** — name follows `xcelr8_{module}_{process}_{entity}`
6. **API needed?** — web only, or API + web
7. **Backpack CRUD?** — admin panel view needed?
8. **Jobs / imports / exports?** — async processing needed?

---

## Scaffold Sequence (Follow in Order)

### 1. Permissions (seed first)

```php
// Create permissions before routes (routes reference them)
Permission::findOrCreate('SLS_BKNG_CREATE', 'web');
Permission::findOrCreate('SLS_BKNG_EDIT', 'web');
Permission::findOrCreate('SLS_BKNG_VIEW', 'web');
Permission::findOrCreate('SLS_BKNG_CANCEL', 'web');
```

### 2. Database Table(s)

Deliver as a guarded Laravel migration (`Schema::hasTable` check, working `down()`), run on local only —
see `.ai/rules/database.md`. The SQL below shows the target shape.
```sql
CREATE TABLE `xcelr8_sales_bookings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `booking_number` VARCHAR(20) NOT NULL UNIQUE,
    `person_code` VARCHAR(20) NULL,          -- code-based link to person
    `vehicle_variant_code` VARCHAR(30) NULL, -- code-based link to variant
    `status` VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    -- Six mandatory audit columns
    `created_at` DATETIME NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `updated_at` DATETIME NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `deleted_at` DATETIME NULL,
    `deleted_by` BIGINT UNSIGNED NULL,
    INDEX `idx_bkng_person` (`person_code`),
    INDEX `idx_bkng_variant` (`vehicle_variant_code`),
    INDEX `idx_bkng_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3. Model

```php
// app/Models/Sales/Booking/Booking.php
namespace App\Models\Sales\Booking;

use App\Models\BaseModel;

class Booking extends BaseModel
{
    protected $table = 'xcelr8_sales_bookings';
    protected $fillable = ['booking_number', 'person_code', 'vehicle_variant_code', 'status'];

    public function person(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Admin\Person::class, 'person_code', 'person_code');
    }
}
```

### 4. Service

```php
// app/Services/Sales/Booking/BookingService.php
namespace App\Services\Sales\Booking;

class BookingService
{
    public function create(array $data): Booking { /* ... */ }
    public function update(Booking $booking, array $data): Booking { /* ... */ }
    public function cancel(Booking $booking): void { /* ... */ }
}
```

### 5. FormRequest(s)

```php
// app/Http/Requests/Sales/Booking/StoreBookingRequest.php
namespace App\Http\Requests\Sales\Booking;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('SLS_BKNG_CREATE');
    }

    public function rules(): array
    {
        return [
            'person_code'          => ['required', 'string'],
            'vehicle_variant_code' => ['required', 'string'],
        ];
    }
}
```

### 6. Controller

```php
// app/Http/Controllers/Sales/Booking/BookingController.php
namespace App\Http\Controllers\Sales\Booking;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService) {}

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookingService->create($request->validated());
        return response()->json(['success' => true, 'data' => $booking], 201);
    }
}
```

### 7. Route File

```php
// routes/sales.php — create if not exists
Route::prefix('sales')->name('sales.')->middleware(['auth'])->group(function () {
    Route::prefix('bookings')->name('booking.')->group(function () {
        Route::get('/', [BookingController::class, 'index'])
            ->middleware('permission:SLS_BKNG_VIEW')
            ->name('index');
        Route::post('/', [BookingController::class, 'store'])
            ->middleware('permission:SLS_BKNG_CREATE')
            ->name('store');
        Route::post('/{booking}/cancel', [BookingController::class, 'cancel'])
            ->middleware('permission:SLS_BKNG_CANCEL')
            ->name('cancel');
    });
});
```

```php
// routes/web.php — add require (if not already there)
require base_path('routes/sales.php');
```

### 8. Backpack CRUD Controller (if needed)

```php
// app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php
namespace App\Http\Controllers\Admin\Sales\Booking;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use App\Models\Sales\Booking\Booking;

class BookingCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;

    public function setup(): void
    {
        CRUD::setModel(Booking::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/sales/booking');
        CRUD::setEntityNameStrings('booking', 'bookings');
    }

    public function index()
    {
        // Inline check as the first statement — never $this->middleware() in setup()
        // (runs too late / doesn't exist on Laravel 11+). See .ai/rules/admin-backpack.md.
        if (! backpack_user()->can('SLS_BKNG_VIEW')) {
            abort(403, 'Unauthorized.');
        }
        // …
    }
}
```

### 9. Blade Views

```
resources/views/sales/booking/
├── list/index.blade.php
├── create/index.blade.php
├── edit/index.blade.php
├── view/index.blade.php
└── cancel/index.blade.php
```

### 10. Post-Scaffold (Always Offer)

```bash
php artisan changelog:add --type=feat --message="Added Sales Booking module (SLS_BKNG)"
php artisan laradocs:make
```

---

## Module Development Checklist

After every scaffold, verify:

- [ ] Module code registered (e.g. `SLS`)
- [ ] Process code registered (e.g. `BKNG`)
- [ ] All permissions created with `MODULE_PROCESS_ACTIVITY` format
- [ ] Table has all 6 audit columns
- [ ] Table has `deleted_at` (soft delete)
- [ ] No SQL FK constraints added
- [ ] Route file is module-specific (not in web.php directly)
- [ ] Routes have permission middleware
- [ ] Controller is thin (delegates to service)
- [ ] FormRequest used for validation and authorization
- [ ] Model extends BaseModel
- [ ] Model name is domain-oriented (no "Model" suffix)
- [ ] Views in correct `module/process/activity/` directory
- [ ] API routes under `api/v1/` with correct naming
- [ ] Swagger docs written for API methods
- [ ] Changelog entry added
- [ ] Laradocs updated

---

## Naming Reference

```
Permission:     SLS_BKNG_CREATE
Route name:     sales.booking.create
Route URI:      /sales/bookings/create
Controller:     App\Http\Controllers\Sales\Booking\BookingController
Request:        App\Http\Requests\Sales\Booking\StoreBookingRequest
Service:        App\Services\Sales\Booking\BookingService
Action:         App\Actions\Sales\Booking\CreateBookingAction
Model:          App\Models\Sales\Booking\Booking
Table:          xcelr8_sales_bookings
View:           resources/views/sales/booking/create/index.blade.php
Job:            App\Jobs\Sales\Booking\SendBookingConfirmationJob
Import:         App\Imports\Sales\Booking\BookingImport
Export:         App\Exports\Sales\Booking\BookingExport
API route:      api.v1.sales.booking.create
API controller: App\Http\Controllers\Api\V1\Sales\Booking\BookingController
Branch:         feature/sales-booking
Commit:         feat(sales-booking): scaffold booking module
```

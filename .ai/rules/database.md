---
description: Database conventions, schema design, Eloquent safety, query patterns. Load for any migration, model, or query work.
paths:
  - app/Models/**
  - database/**
  - app/Services/**
---

# XCELR8 Database Rules

## 1. Two Databases

| DB Connection | Purpose | Key Prefixes |
|---|---|---|
| `xlrm` (primary) | Main application | `xlr8_vehicle_pricing_*`, `xlr8_admin_*`, `xlr8_iam_*`, `xlr8_utils_*`, `xlr8_vehicle_*` |
| `xlrk` (legacy) | Legacy CRM / booking | `xlr8_crm_*`, `xlr8_booking_*` |

> Never use `xlrk` as SSOT for pricing or quotation calculations. It is legacy read-only reference.

---

## 2. Table Naming — Table Prefixes by Domain

```
xlr8_admin_*         Org / admin: branches, locations, depts, persons, employees
xlr8_iam_*           IAM: OTP, device sessions, user device tokens
xlr8_vehicle_*       Vehicle master: segments, sub-segments, models, variants, accessories
xlr8_vehicle_pricing_*  Pricing pipeline: import sessions, profiles, prices, addons, discounts, rules
xlr8_utils_*         Utilities: settings, keyvalues, synonyms, docs, notifications, comms
xlr8_approval_*      Approval Engine (when built — schema not started as of Phase 1 lock)
xlr8_discount_req_*  Discount Requests (when built)
xlr8_sales_*         Sales module (bookings, enquiries, quotations)
xcelr8_{module}_*    Module-namespaced tables for new modules (see conventions.md)
```

All table names: **lowercase, snake_case, plural, module-prefixed.**

---

## 3. Mandatory Audit Columns (ALL business tables)

Every business table MUST have these six columns:

```sql
created_at  DATETIME NULL,
created_by  BIGINT UNSIGNED NULL,
updated_at  DATETIME NULL,
updated_by  BIGINT UNSIGNED NULL,
deleted_at  DATETIME NULL,
deleted_by  BIGINT UNSIGNED NULL
```

Append-only event tables (immutable log tables) may omit `updated_*` and `deleted_*` columns.

---

## 4. Key Schema Rules

```
✅ Soft delete on ALL domain tables
✅ All business models extend BaseModel
✅ Pricing models: array_merge casts with parent (BaseModel)
✅ Relations are CODE-BASED, not integer FK-based (where specified)
✅ MySQL index names must be ≤ 64 characters (MySQL identifier limit)
✅ Charset: utf8mb4_unicode_ci
✅ Engine: InnoDB

❌ No SQL FK constraints on new XCELR8 module tables
❌ No ad-hoc file columns — use Spatie Media Library
❌ No SELECT * / Model::all() on large tables
❌ No direct pivot table writes without going through service
```

---

## 5. Vehicle Relations — Code-Based (Not Integer FK)

```php
// ✅ Code-based relations (XCELR8 standard for vehicle hierarchy)
class VehicleVariant extends BaseModel
{
    protected $table = 'xlr8_vehicle_variant';

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class, 'segment_code', 'code');
    }

    public function subSegment(): BelongsTo
    {
        return $this->belongsTo(SubSegment::class, 'sub_segment_code', 'code');
    }

    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'model_code', 'code');
    }
}

// ❌ Never integer FK for vehicle hierarchy
public function segment(): BelongsTo
{
    return $this->belongsTo(Segment::class, 'segment_id'); // WRONG for vehicle hierarchy
}
```

---

## 6. Model Conventions

```php
<?php

declare(strict_types=1);

namespace App\Models\Sales\Booking;

use App\Models\BaseModel;

class Booking extends BaseModel
{
    protected $table = 'xcelr8_sales_bookings'; // explicit when prefix doesn't match Laravel convention

    protected $fillable = [
        'booking_number',
        'enquiry_id',
        'vehicle_variant_code',
        'customer_person_code',
        'status',
        // ... list explicitly — never use $guarded = []
    ];

    protected $casts = [
        'booking_date' => 'date',
        'amount'       => 'decimal:2',
        'meta'         => 'array',
    ];

    // Domain-oriented model name — NOT BookingModel, NOT XcelrSalesBookingModel
}
```

Model naming: **domain-oriented, PascalCase, no "Model" suffix unless required for disambiguation.**

---

## 7. Query Safety — Patterns

```php
// ✅ Use chunkById for large tables
VehicleVariant::query()
    ->where('status', 'INCOMPLETE')
    ->chunkById(200, function ($variants) {
        // process
    });

// ✅ Cache-first for lookups
$branches = Cache::remember('org.branches.all', 3600, fn() =>
    Branch::query()->active()->get()
);

// ✅ Eager load — never N+1
$bookings = Booking::with(['customer', 'vehicle', 'payments'])->paginate(20);

// ✅ Selective columns
$variants = VehicleVariant::query()
    ->select(['id', 'code', 'model_code', 'status'])
    ->where('segment_code', $segmentCode)
    ->get();

// ❌ Never in production code
Booking::all(); // no constraint on large table
DB::table('xlr8_vehicle_variant')->get(); // bypass Eloquent
```

---

## 8. Transactions

```php
// ✅ Wrap multi-table mutations in a transaction
DB::transaction(function () use ($data) {
    $booking = Booking::create($data['booking']);
    BookingPayment::create([
        'booking_id' => $booking->id,
        'amount'     => $data['amount'],
    ]);
    // audit event fires automatically via BaseModel / laravel-auditing
});
```

---

## 9. Migration Conventions

When user explicitly requests migrations (not raw SQL):

```php
// File naming: {timestamp}_create_xcelr8_sales_bookings_table.php
// Include dropIfExists for fresh-table migrations

Schema::dropIfExists('xcelr8_sales_bookings');
Schema::create('xcelr8_sales_bookings', function (Blueprint $table) {
    $table->id();
    $table->string('booking_number', 20)->unique();
    $table->string('enquiry_code', 20)->nullable()->index('idx_bkng_enq');
    $table->string('person_code', 20)->nullable()->index('idx_bkng_person');
    $table->string('vehicle_variant_code', 30)->nullable()->index('idx_bkng_variant');
    $table->string('status', 20)->default('PENDING');

    // Six audit columns — MANDATORY
    $table->dateTime('created_at')->nullable();
    $table->unsignedBigInteger('created_by')->nullable();
    $table->dateTime('updated_at')->nullable();
    $table->unsignedBigInteger('updated_by')->nullable();
    $table->dateTime('deleted_at')->nullable(); // soft delete
    $table->unsignedBigInteger('deleted_by')->nullable();
    // Note: index names max 64 chars
});
```

> **Default delivery = SQL first.** Migrations only when user explicitly requests `php artisan migrate`.

---

## 10. Foreign Key Naming (Descriptive, not abbreviated)

```sql
-- ✅ Descriptive
booking_id
customer_person_code
vehicle_variant_code
rto_rule_id

-- ❌ Abbreviated (unless intentional DB-standard exception)
bkng_id
cust_id
veh_id
```

---

## 11. Person → Code-Based Linking

Person is the central identity anchor. Child tables link by `person_code`, NOT by integer `person_id`:

```php
// person_code is immutable. Children link via this natural key.
class Employee extends BaseModel
{
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_code', 'person_code');
    }
}
```

## Base model inheritance
All domain models extend App\Models\BaseModel, not Eloquent Model directly, to inherit soft-delete-with-actor, base media collections, audit casts, and shared scopes. Only pure pivot/log tables extend Model directly.

## Accessors and mutators
Use the legacy magic-method style (getXxxAttribute()/setXxxAttribute()), not the Attribute class, for model accessors/mutators.

## Custom casts merge with parent
When a model needs its own casts, override casts(): array and merge with parent::casts(); never add a competing $casts property.

## Query scopes
Add domain filters as plain local scope*() methods on the model. Don't duplicate BaseModel's existing generic scopes (active, inactive, dateRange, createdBy, updatedBy, deletedBy, newest, oldest).

## Model events centralized in BaseModel
Don't add observers or per-model booted() actor-stamping logic; it is centralized in BaseModel::booted() already.

## Media collection naming
Name singleFile() entity-image collections {entity}_image; name proof/attachment collections in kebab-case as {noun}-proof. Don't introduce further casing variants.

## Media conversions
Register a ->width(250) preview and ->width(100) thumbnail conversion pair on media collections, queued by default (don't add nonQueued() without reason).

## Media disk
Always call ->useDisk('public') explicitly on every media collection; don't rely on the package's default disk.

## Media URL retrieval
Retrieve media URLs with getFirstMediaUrl('collection'), not getUrl().

## Media AI tagging stays synchronous
Keep AI tagging synchronous inside DocService::upload(), gated by a SystemSettingService flag, rather than wiring it as a Media Library event/observer; write results to the owning model's own column, not Media's custom_properties.

## Media deletion
Don't add custom media-cleanup observers; call clearMediaCollection() only to replace a singleFile/limited collection on update, and let Media Library's own cascade handle deletion.

## Mass assignment
Use explicit $fillable allow-lists on new domain models. The app/Models/Module/{Booking,Exchange,Finance,Insurance,Rto,Spare}/** family's $fillable=[] + $guarded=['id'] pattern is legacy; don't copy it for new code.

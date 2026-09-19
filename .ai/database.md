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
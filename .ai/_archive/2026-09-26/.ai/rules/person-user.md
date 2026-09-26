---
description: Person/Employee/User identity model, contacts, addresses, banking. Load for any user creation, profile, or contact management work.
paths:
  - app/Models/Admin/Person*
  - app/Models/Admin/Employee*
  - app/Models/User.php
  - app/Services/PersonService.php
  - app/Http/Controllers/**/Person*
  - app/Http/Controllers/**/Employee*
---

# XCELR8 Person / User Identity Rules

## 1. Identity Architecture

```
Person (immutable anchor — person_code)
 ├── PersonContact (phones, emails — primary flag)
 ├── PersonAddress (home, office — primary flag)
 └── PersonBankingDetail (bank accounts)

Employee (hangs off person_code)
 └── User (Laravel auth — hangs off person_code)
      └── UserRoleAssignment (temporal role)
```

**Core principle:** `Person` is the central identity entity. Everything else hangs off the immutable natural key `person_code`. There is deliberately **no integer FK** from child tables back to `xlr8_admin_person.id`. Linking is by `person_code` only.

---

## 2. person_code — The Immutable Natural Key

```php
// person_code: system-generated, immutable, unique
// Format: system-defined (e.g. PRS000001, PRS000002, ...)
// Never allow user to change it once assigned

// Child table relation (code-based, not integer-based)
class Employee extends BaseModel
{
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_code', 'person_code');
    }
}

class User extends Authenticatable
{
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_code', 'person_code');
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'person_code', 'person_code');
    }
}
```

---

## 3. PersonService — Always Use for Person Operations

```php
// ✅ Create / update person via service
$person = app(PersonService::class)->createOrUpdate([
    'first_name' => $data['first_name'],
    'last_name'  => $data['last_name'],
    'dob'        => $data['dob'],
    'phone'      => $data['phone'], // service normalizes this
    'email'      => $data['email'],
]);

// ✅ Phone normalization — let the service handle it
// PersonService handles: Indian 10-digit, country code resolution, format cleaning

// ❌ Never invent phone/PAN/Aadhaar/address sanitization yourself
$phone = preg_replace('/[^0-9]/', '', $request->phone); // WRONG — use PersonService
```

PersonService responsibilities:
- Create/update `Person`, `PersonContact`, `PersonAddress`, `PersonBankingDetail`
- Phone normalization (Indian 10-digit, country code resolution)
- PAN / Aadhaar / address sanitization
- Primary contact/address designation
- Date parsing (safe null handling)

---

## 4. Contact Management Rules

```php
// PersonContact: multiple contacts per person, one marked as primary
// PersonAddress: multiple addresses per person (home/work/other), one primary

// Primary flag management is handled by PersonService
// Never manually flip 'is_primary' flags without going through PersonService
```

---

## 5. Employee vs User

| Entity | Purpose |
|---|---|
| `Person` | Identity anchor — name, DOB, contacts, addresses, banking |
| `Employee` | Employment record — branch, designation, employee_code, joining date, status |
| `User` | Laravel auth — email/password/sanctum, linked to person_code |

- A `Person` can be a Customer without being an Employee or User
- An `Employee` is always a `Person`
- A `User` is always a `Person` (and usually an `Employee`)
- DSAs and Referrers are also `Person` records

---

## 6. UserType (FRS 2026-07-25)

UserType governs what a user can do and see. Resolution:
- Designation → maps to UserType role cluster
- `isSuperAdmin()` → wildcard access
- Data scope applied per `xlr8_admin_user_scopes`

---

## 7. Schema Reference

Key tables:
```
xlr8_admin_person               Core person record
xlr8_admin_person_contacts      Phones, emails (type, value, is_primary)
xlr8_admin_person_addresses     Addresses (type, is_primary)
xlr8_admin_person_banking       Bank accounts
xlr8_admin_employees            Employment records (person_code FK)
users                           Laravel auth users (person_code FK)
xlr8_iam_user_role_assignments  Temporal role assignments
xlr8_admin_user_scopes          Data access scope (branch/location/dept/div)
```

---

## 8. Customers vs Employees

Customers are `Person` records with a different context/type flag. They do NOT get `Employee` records unless they are also employed. The `Person` table is shared — the type flag or role differentiation determines consumer context.

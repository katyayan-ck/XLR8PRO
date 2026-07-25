**FRS + Developer Guide + Cheat Sheet**  
**Person • User • UserType System (XLR8 / BMPL)**  
*Fully updated as of 25-Jul-2026 after all refinements in this conversation*

---
# Introduction
**Existing Person system in XLR8 (summary of design, rules, and usage)**

### Core design philosophy
The person is the **central identity entity**. Everything else (employee, user, contacts, addresses, banking, posts, scopes, hierarchy) hangs off it via the immutable natural key `person_code`. There is deliberately **no integer FK** from child tables back to `xlr8_admin_person.id`. All linking is by `person_code`.

Key rules (explicitly documented in the `Person` model):

- `person_code` is **immutable**. Derived once at creation and never changed thereafter (even if PAN is added later when the record was originally created from Aadhaar).
- Derivation priority for individuals:
  1. PAN
  2. Aadhaar
  3. Fallback `PERS-XXXXXX` (or the older `PRSN#####` style seen in the import)
- For legal entities the priority is PAN → TAN → GST → fallback.
- Mobile / email live **only** in `xlr8_admin_person_contacts`. There are no `mobile` / `email` columns on the person table itself.
- Soft deletes + audit columns (`created_by`, `updated_by`, `deleted_by`) are used everywhere.

### Tables & their contracts

**`xlr8_admin_person`**
- Primary key: `id` (auto-increment)
- Natural / business key: `person_code` (unique, immutable)
- Unique constraints also exist on `pan_no`, `aadhaar_no`, `tan_no`, `gst_no`
- Name fields: `first_name`, `middle_name`, `last_name`, `display_name` (the import splits a full name into these)
- Demographic: gender (enum), dob, marital_status, spouse_name, occupation, entity_type (`individual` | `legal_entity`)
- Soft-delete + audit columns

**`xlr8_admin_person_contacts`**
- Unique on (`person_code`, `data_type`, `contact_type`)
- `data_type`: Mobile | Email | Landline | Fax
- `contact_type`: Primary | Alternate | Office | Home | Emergency
- Business rule: **exactly one Primary** per (`person_code` + `data_type`)
- First record of a given data_type is auto-promoted to Primary (model boot logic)
- `makesPrimary()` demotes the existing Primary to Alternate

**`xlr8_admin_person_addresses`**
- Unique on (`person_code`, `address_type`)
- Address types: Primary, Office, Home, Alternate, Permanent
- First address becomes Primary automatically
- `makePrimary()` demotes the previous Primary

**`xlr8_admin_person_banking_details`**
- Unique on (`person_code`, `account_type`)
- Account types: Primary, Secondary, Joint, Trust
- Account natures: Savings, Current, Salary, NRO, NRE
- First bank record becomes Primary
- `makePrimary()` + `markVerified()` helpers

### Eloquent models
- `Person` – SoftDeletes, InteractsWithMedia, CrudTrait. Has the derivation logic, boot immutability guard, rich accessors (`primary_mobile`, `primary_email`, `primary_address`, `primary_bank`, `allEmails`, `allMobiles`, etc.), and search/individual/legal scopes.
- `PersonContact`, `PersonAddress`, `PersonBankingDetail` – each has the “first becomes Primary + makePrimary()” pattern and soft-delete/audit behaviour.
- All of them inherit the common audit behaviour from `BaseModel` (or implement equivalent boot logic).

### Import path (`StandaloneUsersImport`)
The import is the main bulk creation path you have been using:

1. Derive `person_code` (PAN → Aadhaar → sequential PRSN fallback).
2. `createOrUpdatePerson` – upsert on `person_code`, split full name, write contacts (Primary Mobile + Primary Email), address, and banking.
3. `createOrUpdateEmployee` – links via `person_code`, resolves org codes (branch/location/dept/division/vertical/segment…) via name or code lookup, writes the primary pivots.
4. `createOrUpdateUser` – username = lowercase emp_code, password = hashed mobile (fallback 1234567890), user_type = Associate for RTO/DSA else Emp.
5. `assignPrimaryPost` – creates a post role if needed (with sequence suffix when the base post is already assigned) and writes the primary assignment.

The import is deliberately defensive: it tolerates missing columns, cleans phones, nullifies “N/A”/“-”, and logs every create/update.

### How the rest of the system consumes Person
- `OrgService` uses `Person` heavily for reference lookups (`getReferenceUsers` by mobile), display names, primary mobile/email, hierarchy nodes, and the big `getUsers` / `getUsersForListing` methods that return normalised user + person + scope data.
- Employee and User both have `person_code` FKs (logical).
- Hierarchy (upline/downline), reporting managers, sales consultants, etc. all ultimately resolve through the person → employee → user chain.
- Media collections exist for identity documents and profile photos.

# 1. Updated Functional Requirements Specification (FRS)

### 1.1 Purpose & Scope

The Person / User / UserType system is the **single source of truth for human identity** in the XLR8 platform.

It must support:

- Pure identity records (Person) that may never log in
- System users (login accounts) linked to a Person
- Multiple simultaneous associations (Emp + Cust + DSA + Referrer + …) for the same Person
- One clear Primary association
- Full contact, address, banking and media data
- Fast, flexible lookup by person_code, PAN, Aadhaar, mobile, email, username, employee_code, account number
- Clean profile payload for UI / API / My Profile screens

### 1.2 Core Entities

| Entity | Table | Key | Responsibility |
|--------|-------|-----|----------------|
| **Person** | `xlr8_admin_person` | `person_code` (immutable) | Pure identity (name, PAN, Aadhaar, demographics) |
| **PersonContact** | `xlr8_admin_person_contacts` | person_code + data_type + contact_type | Mobile / Email / Landline / Fax (exactly one Primary per data_type) |
| **PersonAddress** | `xlr8_admin_person_addresses` | person_code + address_type | Addresses (exactly one Primary) |
| **PersonBankingDetail** | `xlr8_admin_person_banking_details` | person_code + account_type | Bank accounts (exactly one Primary) |
| **PersonUserType** | `xlr8_admin_person_user_types` | person_code + user_type | Multi-association layer (Emp, Cust, DSA…) |
| **User** | `users` | id / username | Login account (optional) |
| **Employee** | `xlr8_admin_employee` | code | Organisational employment record (optional) |

All tables use SoftDeletes + full audit columns (`created_by`, `updated_by`, `deleted_by`, timestamps).

### 1.3 Business Rules

1. `person_code` is **immutable**. Derived once (PAN → Aadhaar → fallback) and never changed.
2. A Person may have zero or more UserTypes.
3. Exactly one UserType per Person may be marked `is_primary = true`.
4. A UserType may or may not have a linked `user_id` (login).
5. Contacts / Addresses / Banking follow the “first becomes Primary + makePrimary()” pattern.
6. Mobile numbers are normalised to 10-digit Indian format.
7. Media is handled exclusively via Spatie Media Library (`profile_photos`, `identity_documents`).

### 1.4 Key Workflows

#### A. Create / Update a pure Person (no login)
```
PersonService::upsert([display_name, pan_no / aadhaar, contacts, addresses, banking])
→ optionally PersonUserTypeService::assign(..., userType: 'Cust' | 'DSA' | 'Referrer', userId: null)
```

#### B. Create Employee + Login User (classic import path)
```
1. PersonService::upsert(...)           → creates/updates Person + children
2. Create/Update Employee (person_code)
3. Create/Update User (person_code + employee_code + user_type)
4. PersonUserTypeService::assign(..., 'Emp', userId, isPrimary: true)
```

#### C. Add secondary association later
```
PersonUserTypeService::assign($personCode, 'Cust', $userId = null, isPrimary: false)
```

#### D. Lookup (any identifier)
```
PersonService::find('9587893409')          // mobile
PersonService::find('bmpl-0282')           // username
PersonService::find('BMPL-0282')           // employee_code
PersonService::find('RZSFJ7726R')          // PAN
PersonService::find('467583924393')        // person_code / Aadhaar
```

#### E. Full Profile (My Profile / API)
```
PersonService::get($personCode)
```
Returns clean array containing:
- Identity + employee_code + user_id + username
- primary_mobile / primary_email
- short user_types list
- contacts / addresses / banking (plain arrays)
- media

#### F. Bulk Sync (one-time / after import)
```
PersonUserTypeService::syncFromUsers()     // existing logins → primary Emp/Cust…
PersonUserTypeService::syncFromPersons()   // every Person gets at least one record
```

### 1.5 Non-Functional Requirements

- All multi-table writes run inside DB transactions.
- Soft-delete aware.
- Services are static (consistent with OrgService style).
- Profile payload must be plain PHP arrays (no Eloquent Collections leaked to frontend).

---

# 2. Developer Guide

### 2.1 Service Overview

| Service | Responsibility |
|---------|----------------|
| `PersonService` | Identity + contacts + addresses + banking + profile |
| `PersonUserTypeService` | Multi-association (UserTypes) management + sync |

Both live under `App\Services`.

### 2.2 PersonService – Detailed Usage

#### find()
```php
// Returns Person model or null
$person = PersonService::find('9587893409');               // mobile
$person = PersonService::find('bmpl-0282');                // username
$person = PersonService::find('BMPL-0282');                // employee_code
$person = PersonService::find('RZSFJ7726R');               // PAN
$person = PersonService::find(['pan_no' => 'RZSFJ7726R']);
$person = PersonService::find('SUP001', ['with' => ['contacts', 'employee']]);
```

#### get() – Clean Profile (preferred for UI)
```php
$profile = PersonService::get('467583924393');
/*
[
  'person_code'    => '467583924393',
  'display_name'   => 'Shankar Giri',
  'employee_code'  => 'BMPL-0282',
  'user_id'        => 125,
  'username'       => 'bmpl-0282',
  'primary_mobile' => '9587893409',
  'primary_email'  => '...',
  'user_types'     => [
      ['id' => 12, 'user_type' => 'Emp', 'is_primary' => true],
  ],
  'contacts'  => [...],   // plain arrays
  'addresses' => [...],
  'banking'   => [...],
  'media'     => [
      'profile_photo' => null|url,
      'identity_documents' => [...]
  ]
]
*/
```

#### upsert() – Create or Update + Nested Children
```php
$person = PersonService::upsert([
    'display_name' => 'Ramesh Kumar',
    'pan_no'       => 'ABCDE1234F',
    'gender'       => 'Male',
    'contacts' => [
        ['data_type' => 'Mobile', 'contact_type' => 'Primary', 'contact_detail' => '9876543210'],
        ['data_type' => 'Email',  'contact_type' => 'Primary', 'contact_detail' => 'r@example.com'],
    ],
    'addresses' => [
        ['address_type' => 'Primary', 'address_line_1' => '...', 'city' => 'Jaipur', 'pincode' => '302001'],
    ],
    'banking' => [
        ['account_type' => 'Primary', 'bank_name' => 'SBI', 'account_number' => '123456', 'ifsc_code' => 'SBIN0001234'],
    ],
]);
```

#### Child helpers
```php
PersonService::upsertContact($personCode, [...]);
PersonService::upsertAddress($personCode, [...]);
PersonService::upsertBanking($personCode, [...]);

PersonService::setPrimary('contact', $personCode, $contactId);
PersonService::setPrimary('address', $personCode, $addressId);
PersonService::setPrimary('banking', $personCode, $bankId);
```

### 2.3 PersonUserTypeService – Detailed Usage

```php
// Read
$types   = PersonUserTypeService::getUserTypes($personCode);
$primary = PersonUserTypeService::getPrimary($personCode);
$summary = PersonUserTypeService::getSummary($personCode);   // richer summary

// Write
PersonUserTypeService::assign($personCode, 'Emp', $userId = 42, isPrimary: true);
PersonUserTypeService::assign($personCode, 'Cust', null, isPrimary: false);
PersonUserTypeService::setPrimary($personCode, 'Emp');
PersonUserTypeService::remove($personCode, 'Cust');

// Sync (run after major imports)
PersonUserTypeService::syncFromUsers();
PersonUserTypeService::syncFromPersons();
```

### 2.4 Recommended Integration Pattern

```php
// 1. Ensure Person exists
$person = PersonService::upsert($personData);

// 2. If this is an Employee + Login
//    … create Employee & User records …

// 3. Register the association
PersonUserTypeService::assign(
    $person->person_code,
    'Emp',
    $user->id,
    isPrimary: true
);

// 4. Later add secondary roles
PersonUserTypeService::assign($person->person_code, 'DSA', null, false);
```

### 2.5 Best Practices

- Always use `PersonService::get()` when you need data for the frontend.
- Use `find()` only when you need the Eloquent model for further work.
- Never change `person_code` after creation.
- Prefer `assign(..., isPrimary: true)` over manual `makePrimary()` calls.
- Run both sync methods after bulk user imports.

---

# 3. Cheat Sheet (Quick Reference)

### PersonService

| Method | Returns | Example |
|--------|---------|---------|
| `find($criteria, $options)` | `?Person` | `find('9587893409')`, `find('bmpl-0282')` |
| `search($criteria, $options)` | `Collection` | `search([], ['q' => 'Giri', 'limit' => 20])` |
| `get($personCode, $options)` | `array\|Person\|null` | `get('467583924393')` |
| `upsert($data, $options)` | `Person` | See full example above |
| `upsertContact(...)` | `PersonContact` | — |
| `upsertAddress(...)` | `PersonAddress` | — |
| `upsertBanking(...)` | `PersonBankingDetail` | — |
| `setPrimary($type, $personCode, $id)` | `bool` | `setPrimary('contact', $code, 332)` |

### PersonUserTypeService

| Method | Returns | Example |
|--------|---------|---------|
| `getUserTypes($personCode)` | `Collection` | — |
| `getPrimary($personCode)` | `?PersonUserType` | — |
| `getSummary($personCode)` | `?array` | Clean summary |
| `formatForProfile($personCode)` | `array` | Used internally by PersonService |
| `assign($personCode, $type, $userId, $isPrimary)` | `PersonUserType` | — |
| `setPrimary($personCode, $type)` | `bool` | — |
| `remove($personCode, $type)` | `bool` | — |
| `syncFromUsers()` | `array` stats | Back-fill from users table |
| `syncFromPersons()` | `array` stats | Ensure every Person has ≥1 record |

---

### Next Recommended Actions

1. Replace both service files with the final versions from this conversation (especially the fixed `cleanPhone`).
2. Run the two sync methods once:
   ```php
   PersonUserTypeService::syncFromUsers();
   PersonUserTypeService::syncFromPersons();
   ```
3. Verify with:
   ```php
   PersonService::get('467583924393');
   PersonService::find('9587893409');
   PersonService::find('bmpl-0282');
   ```


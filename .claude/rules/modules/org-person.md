---
description: Person/Employee/User identity and org masters. Load for person, employee, user or org-entity work.
paths:
  - app/Models/Admin/**
  - app/Models/User.php
  - app/Services/PersonService.php
  - app/Services/Org/**
  - app/Services/OrgService.php
  - app/Services/HR/**
  - app/Http/Controllers/Admin/Org/**
---

# Org & identity

## Identity
```
Person (person_code — immutable natural key)
 ├── PersonContact (data_type Mobile/Email/…, contact_type Primary/Alternate/…)
 ├── PersonAddress (address_type Primary/…)      ├── PersonBankingDetail (account_type Primary/…)
 ├── Employee (person_code; code BMPL-####; designation_code, primary_branch_code, primary_dept_code…)
 └── User (person_code, employee_code; Laravel auth)
```
- Children link by `person_code`, never by `person.id`. Customers, DSAs and referrers are Persons too.
- "Primary" is encoded as `address_type` / `account_type` / `contact_type = 'Primary'` (there is no `is_primary` column).
- `Person::primary_mobile` / `primary_email` accessors resolve contacts; `users` has no mobile/email columns.
- Person writes only through `App\Services\Person\{PersonRecord,PersonContact,PersonAddress,PersonBanking}Service`
  (or the `PersonService::upsert*` facade that delegates to them) — DEC-053. Child types are slots (`TypedSlots`):
  blank → Primary if free; Primary promotes (swap); child deletes are permanent (BUG-175).
- Employee / User / UserScope writes only through `Org\EmployeeService`, `IAM\UserService`, `IAM\UserScopeService` (DEC-054);
  the User screen and user importer orchestrate roles/overrides/journey around them, one transaction per save/row.
- Employee–branch/location/department **pivot tables do not exist**; relations on `User`/`Employee` that
  use them are dead (BUG-158). Use `employee.primary_*_code` and `user_scopes`.

## Org masters
- Branch, Location, Department, Division, Vertical, Designation: thin controllers → `App\Services\Org\*Service`
  (`EntityService` subclasses: `fields()` is the only rule set; disable guard via `OrgEntityConcerns`/`OrgEntityGuard`;
  media in `afterSave`) + feature tests — the reference pattern for admin CRUD (DEC-050/052). No FormRequests.
- Read org data through `OrgService` (cached 3600s): `usersByDesignation()`, `getUpline()`, `branches()`…
- Designations double as Spatie roles (see `.ai/rules/modules/iam-rbac.md`).
- Retired concepts: Posts / post assignments (BUG-080), reporting & approval hierarchies (legacy graph), emp pivots.

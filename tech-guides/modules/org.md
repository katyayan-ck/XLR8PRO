# Org — branches, locations, departments, divisions, verticals, designations, employees

The company structure. Every relation points at these entities by **code** (`branch_code`, `dept_code`, …), never by
integer id. Reads go through `OrgService` (cached); writes go through the entity services (DEC-050/052).

| Need | Use |
|---|---|
| dropdown of branches / locations / departments / … | `OrgService::branches()`, `locations($branch)`, `departments()`, `divisions($dept)`, `verticals()` |
| code → name | `OrgService::branchName($code)` and friends |
| people pickers, users by role / branch | `OrgService::teamOptions()`, `usersByDesignation()`, `getUsers(...)` |
| reporting hierarchy | `OrgService::getUpline()`, `getDownline()`, `getDirectReports()` |
| resolve an imported "Name (CODE)" / ALL to codes | `OrgScopeService::resolveCode()`, `resolveLabel()`, `expandCodes()` |
| create / edit / disable an org record | `Org\{Branch,Location,Department,Division,Vertical,Designation,Employee}Service` |

**Never** query `xlr8_admin_*` tables directly for lookups (golden rule 4) — `OrgService` caches them.

---

## Models

| Model | Table | Key | Relations / scopes / accessors | Writer |
|---|---|---|---|---|
| `Admin\Branch` | `xlr8_admin_branch` | `code` (route key) | `locations()`, `primaryEmployees()`; scopes `headOffice()`, `byCity($city)`; `full_address` | `Org\BranchService` |
| `Admin\Location` | `xlr8_admin_location` | `code` | `branch()`; scopes `forBranch($code)`, `salesLocations()`, `workshops()`, `partsLocations()`; `type_tags` (Sales / Workshop / Parts / Stock / Office / MWH / LMM flags) | `Org\LocationService` |
| `Admin\Department` | `xlr8_admin_department` | `code` | `divisions()`, `designationTree()`, `employees()` (pivot `xlr8_admin_emp_department_pivot`); scope `topLevel()` | `Org\DepartmentService` |
| `Admin\Division` | `xlr8_admin_division` | `code`, parent `dept_code` | `department()`, `primaryEmployees()`, `designationTree()`; scope `forDept($code)` | `Org\DivisionService` |
| `Admin\Vertical` | `xlr8_admin_vertical` | `code` (also legacy `vert_code`) | — | `Org\VerticalService` |
| `Admin\Designation` | `xlr8_admin_designation` | `code` — **is the Spatie role** (guard `web`) | `parentDesignation()` / `childDesignations()` (`parent_desig_code`), `employees()`; `rank_label` (A–E); `wouldCreateCircularHierarchy($parentCode)` | `Org\DesignationService` |
| `Admin\DesigDeptTree` | `xlr8_admin_desig_dept_tree` | `tree_code` | `designation()`, `department()`, `division()`, `reportsTo()`, `directReports()`; scopes `active()`, `byDept()`, `byDesig()`, `roots()` | legacy structure table (no service) |
| `Admin\Employee` | `xlr8_admin_employee` | `code` (`BMPL-####`) | `person()`, `designation()` (`designation_code`), `designationLegacy()` (`desig_code`), `divisions()` (pivot with dates); accessors `display_name`, `designation_code`, `designation_name`, `primary_branch_code`, `primary_location_code`, `primary_department_code`, `primary_division_code` | `Org\EmployeeService` |
| `Admin\EmployeeHistory` | `xlr8_admin_employee_history` | `emp_code` + effective dates | `employee()`; scope `activeOn($date)` | written by HR services (see [hr.md](hr.md)); plain `Model` (table has no `deleted_at`) |
| `Admin\UserReporting` | `xlr8_admin_user_reporting` | — | `user()`, `reportsTo()`; scopes `active()`, `forTopic($topic)`, `withScope($type, $code)` | topic-based reporting lines (legacy; approvals use the power sheet instead) |
| `Admin\UserType` | `xlr8_iam_user_type` | `code` | `users()`; `generateCode($prefix = 'UT')` | reference data |
| `Admin\PinCodes` | `bmpl_pincodes` | `id`, tree via `parent` | `parentLocation()`, `childLocations()` — levels STATE → DISTRICT → TEHSIL → POSTOFFICE | reference data |

Mutators: `Department`, `Division` and `Vertical` upper-case their code on assignment (`setCodeAttribute`), and
`Division::setDeptCodeAttribute` upper-cases `dept_code` — the entity services already normalise, these are the backstop.

Person models (`Person`, `PersonContact`, `PersonAddress`, `PersonBankingDetail`, `PersonUserType`) are in
[person.md](person.md); `UserScope` in [iam-auth.md](iam-auth.md).

**Employee fields worth knowing:** `designation_code` (current) and `desig_code` (legacy mirror kept in step),
`primary_branch_code`, `primary_loc_code`, `primary_dept_code`, `primary_div_code`, `vertical_code`, `segment_code`,
`sub_segment_code`, `reporting_manager_code` (an employee code), `employment_type` (`permanent`, `probation`,
`apprentice`, `contract`, `temporary`), `employment_status` (`active`, `inactive`, `separated`, `terminated`,
`absconded`), `joining_date`, `separation_date`, statutory ids (`uan_number`, `esi_number` — unique incl. deleted).

---

## OrgService (`App\Services\OrgService`, all static, cached 1 hour)

### Master lists (active rows only, ordered by name)
| Method | Returns |
|---|---|
| `branches()` | `['JPR' => 'Jaipur', …]` code ⇒ name |
| `locations(?string $branchCode = null)` | code ⇒ name, optionally one branch |
| `branchRows()` | `['JPR' => ['id' => 1, 'code' => 'JPR', 'name' => 'Jaipur', 'short_name' => 'JPR'], …]` (DEC-060) |
| `locationRows(?string $branchCode = null)` | list of full location rows (arrays) |
| `serviceBranches()` | `[locationId => ['id' => …, 'name' => …]]` — active locations with `is_workshop` |
| `locationsByState(int\|string $stateId)` | `Collection<PinCodes>` (`id`, `name`) under a state |
| `departments()`, `divisions(?string $deptCode)`, `verticals()` | code ⇒ name |
| `segments()`, `subSegments(?string $segmentCode)`, `models(?string $segmentCode)` | vehicle code ⇒ name (prefer `VehicleService` options for vehicle screens — [vehicle.md](vehicle.md)) |
| `variants(?string $modelCode)` | `['VAR-CODE' => ['name' => …, 'fuel_type_id' => …, 'fuel_type' => 'Petrol', 'transmission' => id, 'drivetrain' => id, 'seating' => id]]` |
| `colors(?string $variantCode)` | `color_code ⇒ color` from variant rows |

### Name lookups (fall back to the code itself)
`branchName($code)`, `locationName($code)`, `departmentName($code)`, `divisionName($code)`, `verticalName($code)`,
`segmentName($code)`, `subSegmentName($code)`, `modelName($code)`, `variantName($code)` → the variant's display name, or the code when unknown (BUG-186 fixed).

Format helpers: `formatCodeWithName($code, $resolver, 'code'|'name'|'code_name')` → `JPR` / `Jaipur` / `[JPR] Jaipur`;
`formatCodeList($codes, $resolver, $format)` → comma string; `formatReportingManager($empCode, $format)`.

### People
| Method | Returns |
|---|---|
| `teamOptions()` | `[userId => 'Priya Mehta (JPR · SLS_CONS)']` — active users with an employee record; **the people picker** for tasks / tickets / assignments |
| `usersByDesignation($desigCode, $branchCode = 'ALL')` | list of `['id', 'username', 'employee_code', 'person_code', 'display_name']` |
| `usersByDepartment($deptCode, $branchCode = 'ALL', $divCode = 'ALL')`, `usersByDivision($divCode, $branchCode)` | `[userId => 'username (EMP-CODE)']` |
| `salesConsultants($branchCode = 'ALL')` | same shape; designation `CNS` in department `SLS` (hard-coded codes — see BUG-183 for `CNS`) |
| `salesTeamUsers($branchCode = 'ALL')` | everyone in department `SLS` |
| `users(array $filters)` | `[userId => label]`; filters `dept_code`, `div_code`, `desig_code`, `branch_code` |
| `getUsers($branch, $loc, $dept, $div, $desig, $vertical, $segment, $subSegment, $model, $variant, ?$userType, $primaryOnly = false)` | full rows (see below). Each filter is `'ALL'` or a code, matched on the employee's primary column **or** an active `UserScope` (unless `$primaryOnly`) |
| `getUsersForListing(... same ..., $orgFormat, $vehFormat, $managerFormat)` | grid-ready rows with names formatted `code` / `name` / `code_name` (vehicle `name` formats hit BUG-186) |
| `getCurrentUser()` | the signed-in user in the `getUsers` row shape, or null |
| `getUserNameByCode($personOrEmpCode, $colType = null, $default = 'N/A')` | display name; `$colType = 3` looks up a DSA (`Xl_DSA_Master`) by id |
| `getReferenceUsers('Customer'\|'Team Member'\|'Promoter', $mobile)` | who has this mobile: persons / users / DSA promoters, `code ⇒ label` |

`getUsers()` row: `id, username, user_type, employee_code, person_code, display_name, designation_code,
reporting_manager_code, mile_id, primary_branch_code, primary_loc_code, primary_dept_code, primary_div_code,
vertical_code, segment_code, sub_segment_code, branches[], locations[], departments[], divisions[], verticals[],
segments[], sub_segments[], models[], variants[], primary_mobile, primary_email, avatar, avatar_initials, profile_image`.

### Reporting hierarchy (by `employee.reporting_manager_code`)
| Method | Returns |
|---|---|
| `getUpline($username, $maxDepth = 50, $status = 'active', $excludeBypassUsers = false)` | managers from the direct one upwards (nodes below); stops on a cycle or a broken chain (logged) |
| `getDownline($username, $flat = false, $status, $excludeBypassUsers)` | nested tree (`children`) or a flat list |
| `getDownlineCount($username, $status, $excludeBypassUsers)` | int |
| `getDirectReports($username, $status, $excludeBypassUsers)` | nodes at depth 1 |

`$status`: `active` (user active and not separated), `inactive`, `all`. Node: `username, employee_code, display_name,
designation_code, reporting_manager_code, primary_branch_code, primary_loc_code, primary_dept_code, primary_div_code,
is_active, bypass_data_scoping, separation_date, depth`. `$username` is matched against `users.username`
(case-insensitive). The walk looks managers up by their **employee code as username**, which holds because staff
usernames equal their employee codes (all 200 staff users in the test copy) — keep that convention when creating users.

### KeyValue shortcuts (prefer `KeywordValueService` — [utils-legacy.md](../architecture/legacy-utils.md))
`getKeyValuesByCode($keywordCode)` (Collection), `getKeyValuesByColName($keyword)`, `getKeyValueById($id)`,
`getKeyValueByCode($code)`, `keywordValueByCode($keywordCode)` (`[['code' => …, 'value' => …]]`),
`keywordValueByParentCode($keywordCode, $parentValueCode, $parentKeywordCode = null)` (children of a parent value,
`parent_id` is a comma list).

### Pincode, receipts, customers (legacy helpers kept here)
| Method | Returns |
|---|---|
| `getPostOfficesByPincode($pin)` | `[id => 'Post office name']` |
| `getLocationByPincode($pin)` | `['tehsil' => …, 'district' => …, 'city' => <state name>]` — note `city` holds the **state** |
| `checkReceiptX($receiptNo)` | `1` / `0` — receipt number already used in `Bookingamount` |
| `getCustomerByTransactionIds($enqNo, $bookingId, $votfNo)` | `['success' => true, 'enq_id', 'customer_name', 'care_of_type', 'care_of', 'address', 'mobile', 'alternate_mobile', 'booking_no', 'votf_no', 'vehicle_registration_no', …]` or `['success' => false]` — priority enquiry → booking → VOTF |
| `applyHighlightFilter($enquiryQuery, $filter)` | adds the enquiry list "highlight" filter: `missed_fup`, `today_fup`, `birthday`, `anniversary`, `exchange`, `pending_eval`, delayed, wrong assign, in-house finance, lost |

---

## OrgScopeService (`App\Services\OrgScopeService`, static)
Resolves free text (imports, filters, power sheets) to canonical codes across the org and vehicle trees:
`branch → location`, `department → division`, `vertical`, `segment → sub_segment → model → variant`.

| Method | Returns |
|---|---|
| `types()` | `['branch', 'location', 'department', 'division', 'vertical', 'segment', 'sub_segment', 'model', 'variant']` |
| `resolveCode($type, $value)` | canonical upper-case code from a code or a name (case-insensitive); `ALL` / `ANY` → `'ALL'`; null when unknown |
| `resolveLabel($type, 'Jaipur (JPR)')` | `JPR` — the part in brackets is tried as a code first |
| `expandCodes($type, $value, $context = [])` | `ALL` → every active code (optionally under a parent from `$context`); `"JPR, Kota"` → `['JPR', 'KOT']` |
| `firstCode($type, $value)` | first of `expandCodes()` or null |

```php
$branches = OrgScopeService::expandCodes('branch', $row['Branch']);        // "ALL" or "Jaipur, AJM"
$code = OrgScopeService::resolveLabel('location', 'Tonk Road (JPR-TR)');   // JPR-TR
```

---

## Entity services (writes)
All extend `EntityService` (see [core.md](../architecture/core.md)): `create()`, `update()`, `upsert()`, `validate()`, `describe()`.

| Service | Fields (required*) | Rules |
|---|---|---|
| `Org\BranchService` | `code`* (3–10, immutable), `name`*, `description`, `phone`, `email`, `address`, `city`, `state`, `pincode`, `latitude`, `longitude`, `is_head_office`, `is_active`, image / documents | one head office only; cannot disable while locations / employees depend on it |
| `Org\LocationService` | `branch_code`*, `code`* (≤100), `name`*, contact / address fields, `is_active`, flags `is_sales_location`, `is_workshop`, `is_parts_location`, `is_stock_location`, `is_office_only`, `is_mwh`, `is_lmmws` | dependency-checked disable |
| `Org\DepartmentService` | `code`* (2–10), `name`*, `description`, `is_active` | creating a department also creates a same-coded default division |
| `Org\DivisionService` | `dept_code`*, `code`* (2–10), `name`*, `description`, `is_active` | an active division needs an active department |
| `Org\VerticalService` | `code`* (2–10), `name`*, `description`, `is_active` | dependency-checked disable |
| `Org\DesignationService` | `code`* (≤50), `name`*, `description`, `rank` (0 none, 1 = A highest … 5 = E), `is_top_mgmt`, `parent_desig_code` (reports to), `is_active` | reports-to must be the same or higher rank; no circular chain; also `syncPermissions($designation, $codes)` and `currentPermissionCodes($designation)` (designation = role) |
| `Org\EmployeeService` | `code` (generated `BMPL-####` via `nextCode()` when blank, immutable), `person_code`* (immutable), `designation_code`, primary org codes, `reporting_manager_code`, `employment_type`*, `employment_status`*, dates, statutory and payroll fields | `separation_date ≥ joining_date`; `desig_code` mirrors `designation_code`; UAN / ESI unique incl. deleted |

`OrgEntityGuard` (static): `activeDependents($code, $dependents)` lists active rows still pointing at a code;
`blockersForDisabling($wasActive, $willBeActive, $code, $dependents)` → messages when disabling would orphan them.

```php
$branch = app(BranchService::class)->create(['code' => 'kot', 'name' => 'kota', 'pincode' => '324001']);   // code KOT, name Kota
app(DesignationService::class)->syncPermissions($designation, ['SLS_BKNG_VIEW', 'SLS_BKNG_EDIT']);
$emp = app(EmployeeService::class)->create(['person_code' => 'P000123', 'designation_code' => 'SLS_CONS',
    'primary_branch_code' => 'JPR', 'employment_type' => 'permanent', 'employment_status' => 'active', 'joining_date' => '2026-04-01']);
```

## Use cases
**Branch + location cascading dropdowns**
```blade
<x-ui.select name="branch_code" :options="OrgService::branches()" placeholder="Branch" />
{{-- AJAX endpoint returns OrgService::locations($branch) as Select2 {results:[{id,text}]} --}}
```

**Notify all managers in a branch** → use `Audience::designation('SLS_MGR')->andBranch('JPR')` (tech-guides/platform/02) rather
than `usersByDesignation()` loops.

**Show "reports to" chain on a profile**
```php
$chain = collect(OrgService::getUpline($user->username))->pluck('display_name');   // direct manager first
```

## Gotchas
- Caches last an hour and are **not** busted by the entity services; after bulk master changes run
  `php artisan cache:clear` (or wait). Designation / branch edits in the admin screens are rare enough that this is accepted.
- `salesConsultants()` hard-codes `CNS` / `SLS`; prefer permission- or designation-driven audiences.
- `getLocationByPincode()` returns the state under the key `city`.
- Codes are the keys everywhere — never store an org **id** in a new column.

## Testing
Use real org rows from `xlrm_testing` (e.g. `Branch::query()->active()->value('code')`) and the entity services for
new rows inside `DatabaseTransactions`. Remember `OrgService` caches: tests use the array cache, so each test starts cold.

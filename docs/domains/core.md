# Core — BaseModel, User, traits, entity services, Result, helpers

The foundation every module builds on. Read this first; the domain guides assume it.

| Piece | Where | One line |
|---|---|---|
| `BaseModel` | `app/Models/BaseModel.php` | parent of every business model: soft deletes, actor stamping, media, generic scopes, column transformations |
| `User` | `app/Models/User.php` | login account (Backpack + Sanctum), roles = designations, scopes, person / employee links |
| `HasColumnTransformations` | `app/Models/Traits/` | declarative cleanup of column values on write (and optionally read) |
| `HasTreeStructure` | `app/Models/Traits/` | parent / children / materialised path for tree tables |
| `ScopedQuery` | `app/Models/Traits/` | row-level data scope global scope — **not switched on yet** (BUG-083) |
| `HasCommunications`, `HasDocuments` | `app/Models/Traits/` | opt a model into Chat / Docs — `commMaster()`, `getOrCreateCommMaster()`, `recordEvent($action, $summary, $meta = [], ?$body = null)`, `addRemark()`, `history()`, `addHistory()` (legacy) / `documents()`, `attachDocument()`, `documentsList()`; see `docs/utilities/03-chat.md`, `04-docs.md` |
| `EntityService` + `Field` | `app/Support/Entity/` | **the only write path** for master / entry data (DEC-050) |
| `Result` | `app/Support/Result.php` | return value of platform services |
| helpers | `app/Support/helpers.php` | `site_date()`, `site_datetime()`, `setting()`, `feature()` |
| `DateFormatService` | `app/Services/DateFormatService.php` | the site date format (display only) |

---

## BaseModel

Extend it for every business table:
```php
class Branch extends BaseModel
{
    protected $table = 'xlr8_admin_branch';
    protected $fillable = ['code', 'name', /* … */];
    protected string $entityService = \App\Services\Org\BranchService::class;   // DEC-050 backstop reads the same field definitions
}
```

**What you get automatically**
- **Soft deletes** (`deleted_at`), and actor stamping from the signed-in admin / API user: `created_by` and `updated_by`
  on create, `updated_by` on update, `deleted_by` on soft delete (cleared on restore). Force delete stamps nothing.
- **Media collections** (Spatie): `documents` (pdf, jpeg, png, doc, docx), `photos` (jpeg, png, gif, webp) and
  `attachments` (any), all on the `public` disk. Business documents should go through Docs instead (`HasDocuments`).
- **Date storage format** `Y-m-d H:i:s` (stored UTC; display with `site_date()`).
- **Backpack** `CrudTrait` (so any model can back a CRUD panel).

**Relations**
| Method | Returns |
|---|---|
| `createdByUser()` / `updatedByUser()` / `deletedByUser()` | `BelongsTo User` on `created_by` / `updated_by` / `deleted_by` |
| `getDateFormat()` | `'Y-m-d H:i:s'` (storage format) |

**Scopes** (use as `Model::query()->active()->newest()`)
| Scope | SQL |
|---|---|
| `active()` / `inactive()` | `is_active = 1 / 0` — only on tables that have `is_active` |
| `onlyTrashed()` / `includingTrashed()` / `onlyRestored()` | soft-delete filters. `onlyRestored` = live rows with `deleted_by` set — but `restore()` clears `deleted_by`, so it misses model restores (BUG-185) |
| `newest()` / `oldest()` | order by `created_at` desc / asc |
| `dateRange($column, $from, $to)` | `whereBetween($column, [$from, $to])` — pass ISO dates |
| `createdBy($userId)` / `updatedBy($userId)` / `deletedBy($userId)` | audit filters |

**Audit helpers**
| Method | Returns |
|---|---|
| `getCreationDetails()` | `['created_at' => ISO8601, 'created_by_id' => id, 'created_by_name' => …]` — the name currently always reads "System" (BUG-184) |
| `getUpdateDetails()` / `getDeletionDetails()` | same shape for update / delete (`null` when never deleted) |
| `getAllAuditDetails()` | the three above in one array |
| `getCreatedAtForHumans()` / `getUpdatedAtForHumans()` | "3 hours ago" |
| `isRecentlyCreated($minutes = 5)` / `isRecentlyUpdated($minutes = 5)` | bool |
| `isSoftDeleted()` / `wasEverDeleted()` | bool |

```php
// "records created by me this month, newest first"
Branch::query()->createdBy(backpack_user()->id)->dateRange('created_at', now()->startOfMonth(), now())->newest()->get();
```

**Gotchas**
- `active()` fails on tables without `is_active` — check the table card in `.ai/knowledge/db/`.
- Don't set `created_by` / `updated_by` yourself; jobs without a signed-in user leave them null.

---

## User (`App\Models\User`, table `users`)

Login account for the admin panel (guard `backpack`) and the mobile API (Sanctum). Fields:
`username, password, user_type, person_code, employee_code, user_type_id, avatar, is_active, bypass_data_scoping,
last_login_at`. Writes go through `App\Services\IAM\UserService` (entity service).

**Relations**
| Method | Returns |
|---|---|
| `employee()` | `BelongsTo Employee` on `employee_code → code` |
| `person()` | `BelongsTo Person` on `person_code` |
| `scopes()` / `activeScopes()` | `HasMany UserScope` (all / active) — data-scope grants |
| `deviceTokens()` | `HasMany UserDeviceToken` (FCM) |
| `permissionDenials()` | `HasMany UserPermissionDenial` — role permissions revoked for this user only |
| `divisions()` | `BelongsToMany Division` through `xlr8_admin_emp_division_pivot` |
| Spatie `roles()` / `permissions()` | roles **are designations** (`xlr8_admin_designation`), guard `web` |
| Sanctum `tokens()` | API tokens (abilities carry `device_id:<id>`) |

**Identity & org helpers**
| Method / attribute | Returns |
|---|---|
| `display_name` | person's display name, else the employee's person, else username |
| `avatar_initials` | 1–2 letters for avatars |
| `primary_designation` | designation name of the employee, or null |
| `primary_mobile` / `primary_email` | from Person contacts (primary) |
| `all_mobiles` / `all_emails` / `all_addresses` / `all_banking` | collections from Person |
| `primaryBranchCode()` / `primaryLocationCode()` / `primaryDepartmentCode()` / `primaryDivisionCode()` / `primaryPost()` | employee's primary org codes |
| `isEmployee()` | has an employee record |
| `access_profile` | array summary used by the user show page |

**Authorisation helpers**
| Method | Returns |
|---|---|
| `isSuperAdmin()` | has the `superadmin` role — bypasses every permission (Gate `before` hook) |
| `can('SLS_BKNG_VIEW')` | Laravel gate (Spatie permission + superadmin bypass − denials) |
| `deniesPermission($name)` | the permission is explicitly revoked for this user |
| `permissionOverrides()` | `['added' => [...], 'removed' => [...]]` vs role-inherited permissions |
| `bypassesDataScoping()` | `bypass_data_scoping` flag (distinct from superadmin — pitfall P-16) |
| `hasScope($type, $code)` / `getScopeCodes($type)` / `getAllScopes()` / `all_access_scopes` | data-scope grants (`BRANCH`, `LOCATION`, …) |
| `getOrCreateNotificationsMaster()` | inbox counters row (Notify) |

```php
$user = backpack_user();
if (! $user->can('SLS_BKNG_EDIT')) { abort(403); }                          // permission check (first line of an action)
$branches = $user->isSuperAdmin() ? null : $user->getScopeCodes('BRANCH');     // data scope for a report
Notify::to($user->id)->title('Hi {actor}')->send();
```

**Gotchas**
- Never check role names in code — check permissions (`can()`).
- `isSuperAdmin()` ≠ `bypassesDataScoping()`.
- Create / update users only through `UserService` (see [iam-auth.md](iam-auth.md)).

---

## HasColumnTransformations (trait on BaseModel and User)

Declare how columns are cleaned; runs on create, and on update **for changed columns only** (BUG-176):
```php
protected array $columnTransformations = [
    'code'  => 'uppercase_alphanumeric_dash',
    'name'  => ['trim_spaces', 'title_case'],
    'email' => 'lowercase',
    'notes' => ['callback' => 'strip_tags'],
    'ref'   => ['regex' => '/[^A-Z0-9]/', 'replacement' => ''],
];
protected bool $transformOnRead = false;   // true = also transform on attribute read
```
Models with `$entityService` take their pipelines from the entity service's `Field` definitions instead (one source).

**Steps:** `uppercase`, `uppercase_alphanumeric`, `uppercase_alphanumeric_dash`, `uppercase_alphanumeric_underscore`,
`uppercase_alphanumeric_dash_underscore`, `lowercase` (+ the same `_alphanumeric…` family and `…_dash_dot`),
`title_case` (keeps business acronyms from `TITLE_CASE_ACRONYMS`: GM, RTO, OTF, KYC, …), `sentence_case`,
`capitalize_first`, `alphanumeric`, `numeric`, `alpha`, `slug`, `snake_case`, `kebab_case`, `camel_case`,
`pascal_case`, `trim`, `trim_spaces`, `strip_tags`, `mask_email`, `mask_phone`, `json_encode`, `json_decode`,
`truncate:N`, plus `['regex' => …, 'replacement' => …]` and `['callback' => callable]`.

| Method | Use |
|---|---|
| `applyColumnTransformations(?array $onlyColumns = null)` | run the rules now (all, or the given columns) |
| `transformRaw($value, $steps)` | transform an arbitrary string: `$m->transformRaw('  hello WORLD ', ['trim_spaces', 'title_case'])` → `Hello World` |

The transformer never blanks a value: if a step produces an empty string the original is kept. With
`$transformOnRead = true` the trait's `getAttribute($key)` override also transforms on read.

---

## HasTreeStructure (tree tables with `parent_id`, `level`, `path`)

| Method | Returns |
|---|---|
| `parent()` / `children()` | relations |
| `ancestors()` / `descendants()` / `siblings()` | collections |
| `isAncestorOf($node)` / `isDescendantOf($node)` | bool |
| `breadcrumb(' > ')` | "Root > Child > Me" |
| `static tree()` | nested array of the whole table |
| scopes `roots()`, `byLevel($n)` | level filters |

## ScopedQuery (data scoping — dormant)
`bootScopedQuery()` adds a global scope filtering rows by the user's `UserScope` grants. **Not enabled on any model yet** (decision pending,
BUG-083). `Model::withoutDataScope()` gives an unscoped query; `shouldBypassDataScope()` is true for superadmin /
`bypass_data_scoping`. Jobs must never depend on a user scope.

---

## EntityService + Field — the only write path (DEC-050)

Every master / entry table has one `App\Support\Entity\EntityService` subclass. CRUD screens, imports, APIs, jobs and
seeders call it; nothing else validates, transforms or writes those fields.

**API**
| Method | Returns / throws |
|---|---|
| `create(array $input): Model` | normalises → validates → `beforeCreate` → saves → `afterSave` (one transaction); throws `ValidationException` |
| `update(Model $model, array $input): Model` | only fields present in the input; immutable fields ignored; only **changed** values are validated (DEC-054) |
| `upsert(array $input, ?array $key = null): Model` | create or update by natural key (default `code`) — imports |
| `firstOrCreate(array $match, array $values = []): Model` | match compared in normalised form |
| `validate(array $input, ?Model $current = null): array` | normalised, validated data (no save) |
| `normalise(array $input): array` | transformations only; unknown keys dropped, blank strings → null |
| `rules($data, $current)`, `labels()`, `transformations()`, `describe()` | introspection — `describe()` powers import templates / help |

**Writing one**
```php
/** @extends EntityService<Department> */
class DepartmentService extends EntityService
{
    protected function model(): string { return Department::class; }

    public function fields(): array
    {
        return [
            Field::code('code', 10)->label('Department Code')->required()->unique()->immutable(),
            Field::name('name')->label('Department Name')->required(),
            Field::text('description', 5000),
            Field::flag('is_active')->label('Active'),
        ];
    }

    protected function beforeUpdate(Model $model, array &$data): void
    {
        if (($data['is_active'] ?? true) === false && $model->employees()->exists()) {
            $this->fail('is_active', 'Employees still belong to this department.');   // ValidationException on that field
        }
    }

    protected function afterSave(Model $model, array $input, bool $created): void { /* child rows, media — same transaction */ }
}
```

**Field factories:** `make`, `code($name, $max)` (A-Z 0-9 - _, spaces → hyphen), `name($name, $max)` (Title Case, acronyms
kept), `text`, `flag($name, $default)` (1/0, yes/no, on/off), `integer($name, $min)`, `number($name, $min)`, `percent`,
`reference($name, $table, $max, $column = 'code')` (exists, not deleted), `phone` (cleaned to 10 digits), `email`,
`pincode`, `coordinate($name, $limit)`, `choice($name, $allowed)` (case-insensitive, stored canonical), `date` (accepts
Excel serials and common formats), `image`, `documents`, `json`, `scope($name, $max, $synonymType, $anyIsBlank)`
(scope columns for rules: `ANY` → blank, synonyms resolved).
**Modifiers:** `label()`, `format()` (help text), `transform(...)`, `rules(...)`, `each(...)` (array items), `required()`,
`immutable()`, `default($v)`, `unique($scope = [], $table, $column, $includeTrashed)`, `raw()` (no transform),
`virtual()` (validated, not a column — uploads, remove flags).

**Use it**
```php
$dept = app(DepartmentService::class)->create(['code' => 'sales ops', 'name' => 'sales ops']);   // → code SALES-OPS, name Sales Ops
app(DepartmentService::class)->update($dept, ['name' => 'Sales Operations']);
try { $svc->create($row); } catch (ValidationException $e) { $errors = $e->errors(); }            // imports: collect per row
```
The full list of entity services is in `.ai/rules/services.md` and [reference.md](reference.md).

**Gotchas**
- Never `Model::create()`, `DB::table()->insert()` or FormRequest rules for entity fields.
- Business rules go in `beforeCreate` / `beforeUpdate` via `fail()`; side effects in `afterSave`.

---

## Result (`App\Support\Result`)
Returned by platform services (and new services that report business failures without exceptions).
```php
Result::ok(['id' => 5]);                          // ok=true, code OK
Result::fail('NO_RULE', 'No approval rule …');    // ok=false
$r->ok; $r->code; $r->message; $r->data; $r->get('id', null); $r->toArray(); json_encode($r);
```
Use it in new services when callers must branch on a business outcome; keep exceptions for bugs / invalid input.

## Helpers (`app/Support/helpers.php`) and DateFormatService
| Helper | Returns |
|---|---|
| `site_date($date, $fallback = 'N/A')` / `@sitedate($date)` | date in `display.date_format` (default `d-M-Y`) |
| `site_datetime($date, $fallback = 'N/A')` / `@sitedatetime($date)` | + `display.time_format` (default `H:i`) |
| `setting($key, $default)` / `feature($key, $default = false)` | Settings (docs/utilities/01) |

`DateFormatService`: `phpFormat()`, `phpDateTimeFormat()`, `format($date, $fallback)`, `formatDateTime($date, $fallback)`,
`isoFormat($withTime)` (Carbon `isoFormat` tokens, used for Backpack columns). Display only — store and submit ISO.
Never `->format('d-m-Y')` for display.

## Testing
Tests run on `xlrm_testing` with `DatabaseTransactions` (`.ai/rules/testing.md`). For entity services assert the
normalised value and the `ValidationException` field: `$this->expectException(ValidationException::class)` or
`try … catch` and `assertArrayHasKey('code', $e->errors())`.

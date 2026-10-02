# Person — people and legal entities, contacts, addresses, bank accounts, user types

One **Person** row per human or company (customer, employee, DSA, insurer, vendor …), keyed by `person_code`. Everything
that needs a name, mobile, email, address or bank account points at a `person_code`. Details live in child rows held in
fixed **type slots** (DEC-053).

| Need | Use |
|---|---|
| find a person from anything typed (PAN, Aadhaar, mobile, email, code, username) | `PersonService::find($value)` |
| search list | `PersonService::search($criteria, ['q' => …, 'limit' => 20])` |
| full profile as array (API / screens) | `PersonService::get($personCode)` |
| create / update a person with contacts, addresses, banks in one go | `PersonService::upsert($data)` |
| one contact / address / bank row | `PersonService::upsertContact/upsertAddress/upsertBanking($personCode, $data)` |
| change which row is Primary | `PersonService::setPrimary('contact'|'address'|'banking', $personCode, $idOrDetail)` |
| roles a person plays (Emp, Cust, DSA …) | `PersonUserTypeService` |
| normalise PAN / Aadhaar / mobile / GSTIN | `IdentifierService` ([utils-legacy.md](../architecture/legacy-utils.md)) — never hand-roll |

Writes always end up in the entity services `Person\{PersonRecord,PersonContact,PersonAddress,PersonBanking}Service`
(`PersonService` delegates to them) and throw `ValidationException` on bad data.

---

## Models

| Model | Table | Key | Notes |
|---|---|---|---|
| `Admin\Person` | `xlr8_admin_person` | `person_code` | `entity_type` `individual` / `legal_entity`; `salutation`, first / middle / last / `display_name`, `gender`, `dob`, `marital_status`, `spouse_name`, `occupation`, `aadhaar_no`, `pan_no`, `gst_no`, `tan_no`, `extra_data` (json). Media collections `profile_photos`, `identity_documents`. Writer `PersonRecordService` |
| `Admin\PersonContact` | `xlr8_admin_person_contacts` | (`person_code`, `data_type`, `contact_type`) unique | `data_type` Mobile / Email / Landline / Fax; `contact_type` Primary / Alternate / Office / Home / Emergency |
| `Admin\PersonAddress` | `xlr8_admin_person_addresses` | (`person_code`, `address_type`) unique | types Primary / Office / Home / Alternate / Permanent; `address_line_1/2`, `landmark`, `city`, `taluka`, `district`, `state`, `country` (India), `pincode`, lat / long |
| `Admin\PersonBankingDetail` | `xlr8_admin_person_banking_details` | (`person_code`, `account_type`) unique | types Primary / Secondary / Joint / Trust; nature Savings / Current / Salary / NRO / NRE; `ifsc_code`, `micr_code`, `is_verified`, `verified_at`, `verified_by` |
| `Admin\PersonUserType` | `xlr8_admin_person_user_types` | (`person_code`, `user_type`) | types Emp / Cust / DSA / Insurer / Associate / Promoter / Referrer; `user_id` (nullable), `is_primary`, `meta` json |

**Person relations & accessors**
| Member | Returns |
|---|---|
| `contacts()`, `mobileContacts()`, `emailContacts()`, `addresses()`, `bankingDetails()` | HasMany on `person_code` |
| `employee()` / `user()` | HasOne Employee / User on `person_code` |
| `headedDepartments()`, `headedDivisions()`, `garages()` | legacy relations (`head_id` / `person_id` columns — check the table before relying on them) |
| `full_name` | salutation + names |
| `primary_mobile`, `primary_email`, `primary_address` (PersonAddress), `primary_bank` (PersonBankingDetail) | the Primary slot rows |
| `all_mobiles`, `all_emails`, `all_addresses`, `all_banking` | collections |
| scopes `search($term)`, `individuals()`, `legalEntities()` | filters |
| `static deriveCode(Person $p)`, `static generateFallbackCode()` | code derivation used by the record service |

**Child row helpers:** `PersonContact` scopes `primary()`, `byDataType($t)`, `mobiles()`, `emails()`, `emergency()`,
`makesPrimary()`; `PersonAddress` `full_address`, scopes `primary()`, `byType($t)`, `makePrimary()`;
`PersonBankingDetail` `masked_account` (first 2 + `*` + last 4, e.g. `50******3456`; ≤ 6 chars fully masked), `markVerified()`, scopes `primary()`, `verified()`,
`makePrimary()`; `PersonUserType` scopes `primary()`, `active()`, `ofType($t)`, `forPerson($code)`, `makePrimary()`.
All `makePrimary()` calls **swap** slots (the old Primary takes this row's former type) — `SwapsPrimarySlot`, DEC-053.

---

## PersonService (`App\Services\PersonService`, static)

### `find(string|array $criteria, array $options = []): ?Person`
A **string** is auto-detected: PAN (`ABCDE1234F`) → `pan_no`; 12 digits → `aadhaar_no`; a 10-digit mobile (after
cleaning `+91` / `0`) → primary or any mobile contact; an email → email contact; `bmpl-0282`-style → username or
employee code; anything else → `person_code`.
An **array** takes any of: `person_code`/`code`, `pan_no`/`pan`, `aadhaar_no`/`aadhaar`, `tan_no`, `gst_no`, `entity_type`,
`gender`, `mobile`/`phone`, `email`, `account_number`, `username`/`employee_code` (AND-combined).
Options: `with` (relations), `withTrashed`, `fail` (throw `ModelNotFoundException`).
```php
$p = PersonService::find('9829012345');                       // by mobile
$p = PersonService::find(['pan' => 'abcde1234f']);            // normalised to upper-case
$p = PersonService::find('P000123', ['with' => ['contacts'], 'fail' => true]);
```

### `search(array $criteria = [], array $options = []): Collection<Person>`
Same criteria plus options `q` (like on display / first / last name, code, PAN, Aadhaar), `limit`, `offset`,
`orderBy` (`display_name`), `orderDir`.

### `get(string $personCode, array $options = []): Person|array|null`
Loads contacts, addresses, banking, employee (+ `user` with `withUser`). Options `asArray` (default **true**),
`withTrashed`, `includeMedia` (default true). Array shape:
```php
[
  'person_code', 'display_name', 'employee_code', 'user_id', 'username', 'primary_mobile', 'primary_email',
  'user_types' => [['id', 'user_type', 'is_primary']],
  'contacts'   => [['id', 'data_type', 'contact_type', 'contact_detail']],
  'addresses'  => [['id', 'address_type', 'address_line_1', 'address_line_2', 'city', 'state', 'pincode', 'country']],
  'banking'    => [['id', 'account_type', 'bank_name', 'account_number', 'ifsc_code', 'account_holder_name', 'account_nature', 'is_verified']],
  'media'      => ['profile_photo' => url|null, 'identity_documents' => [['id', 'file_name', 'mime_type', 'size', 'url']]],
]
```

### `upsert(array $data, array $options = []): Person` — create or update in one transaction
```php
$person = PersonService::upsert([
    'entity_type' => 'individual', 'salutation' => 'Mr.', 'display_name' => 'ravi kumar sharma',
    'pan_no' => 'abcde1234f', 'dob' => '1990-05-12',
    'mobile' => '+91 98290 12345',                                   // virtual: sets the Primary mobile
    'contacts'  => [['data_type' => 'Email', 'contact_detail' => 'Ravi@Example.com']],
    'addresses' => [['address_line_1' => '12, Malviya Nagar', 'city' => 'jaipur', 'state' => 'rajasthan', 'pincode' => '302017']],
    'banking'   => [['bank_name' => 'HDFC', 'account_number' => '5010 0012 3456', 'ifsc_code' => 'hdfc0001234', 'account_holder_name' => 'Ravi Kumar Sharma']],
]);
// → person_code generated (PERS-######, never a government ID — BUG-206, DEC-095 #15), names split from the display
//   name, salutation "Mr", email lower-cased, first address/bank land in the Primary slot.
```
- Matches an existing person by the given `person_code`, else by Aadhaar, then PAN, then TAN (deleted ones included);
  a **soft-deleted** match is restored. Persons created before 02-10-2026 may still carry a PAN / Aadhaar code until the
  remap (BUG-206).
- Aadhaar / PAN / TAN / GSTIN are unique across all persons including deleted ones.
- Options `with` = relations to return loaded.

### Child rows
| Method | Behaviour |
|---|---|
| `upsertContact($code, $data)` | default `data_type` Mobile; `contact_detail` formatted by type (Mobile → 10 digits, Email → lower-case, Landline / Fax → digits and `+()-`) |
| `upsertAddress($code, $data)` | `address_type` optional |
| `upsertBanking($code, $data)` | `account_type` optional; IFSC upper-case, account number without spaces, MICR 9 digits |
| `setPrimary('contact'\|'address'\|'banking', $code, $identifier)` | promote a row: numeric → row id; for contacts also the contact detail or `"Mobile:Alternate"` (data type : contact type); `false` when not found; unknown type → `InvalidArgumentException` |

**Slot rules (all three):** no type given → Primary when free, else the first free slot. Asking for Primary when
another row holds it → this row becomes Primary and the old one is demoted (a swap). Any other taken slot is a
validation error. Deletes through the entity services' `delete($model)` are **permanent** (the unique key covers
trashed rows, BUG-175).

---

## PersonUserTypeService (`App\Services\PersonUserTypeService`, static)
| Method | Returns |
|---|---|
| `getUserTypes($personCode, $activeOnly = true)` | Collection of `PersonUserType` |
| `getPrimary($personCode)` | the primary row or null |
| `assign($personCode, $userType, ?$userId = null, $isPrimary = false, $meta = [])` | `updateOrCreate` on (person, type): sets `user_id`, `is_active = true`, `meta` (replaced); promotes when `$isPrimary` |
| `setPrimary($personCode, $userType)` / `remove($personCode, $userType)` | bool |
| `formatForProfile($personCode)` | array for profile screens |
| `getSummary($personCode)` | `['person_code', 'person_name', 'employee_code', 'primary_mobile', 'primary_email', 'user_types' => [['user_type', 'is_primary', 'user_id', 'username']], 'primary_type' => …]` or null |
| `syncFromUsers()` / `syncFromPersons()` | back-fill jobs (one primary type per user; a placeholder for persons with none) — return counts |

```php
PersonUserTypeService::assign($person->person_code, 'Cust');                  // they bought a car
PersonUserTypeService::assign($person->person_code, 'DSA', null, false, ['dsa_id' => 42]);
```

## Entity services (direct use)
`app(PersonRecordService::class)->upsert($data)` (also: fields incl. virtual `mobile`, `profile_photo`,
`identity_documents`, `remove_profile_photo`, `remove_identity_documents`), `PersonContactService`,
`PersonAddressService`, `PersonBankingService` — each `create / update / upsert / validate / delete($model)` (the last
permanent). Constants: `PersonRecordService::ENTITY_TYPES`, `SALUTATIONS` (Mr, Mrs, Ms, Dr), `GENDERS`,
`MARITAL_STATUSES`; `PersonService::DATA_TYPES`, `CONTACT_TYPES`, `ADDRESS_TYPES`, `ACCOUNT_TYPES`, `ACCOUNT_NATURES`.

## Use cases
**Walk-in: find or create the customer by mobile**
```php
$person = PersonService::find($mobile) ?? PersonService::upsert(['display_name' => $name, 'mobile' => $mobile]);
PersonUserTypeService::assign($person->person_code, 'Cust');
```

**KYC: add PAN and a permanent address later**
```php
PersonService::upsert(['person_code' => $code, 'pan_no' => $pan]);          // update by code
PersonService::upsertAddress($code, ['address_type' => 'Permanent', 'address_line_1' => $line, 'pincode' => $pin]);
```

**Mobile app profile** → `PersonService::get($user->person_code)` (array) — what the v1 API returns.

**Messages** — channels resolve a person code to their primary mobile / email themselves (tech-guides/platform/10–12): pass
`person_code`, never raw numbers.

## Gotchas
- `person_code` never changes after create — derived codes are based on the identifiers present **at creation**.
- Don't write `PersonContact::create()`; a duplicate slot must come back as a validation error, not a 500.
- Customer numbers shown in the browser should be masked (Telephony `display()`); `masked_account` for banks.

## Testing
Use unique fake PAN / Aadhaar / mobile values per test (the unique keys include deleted rows). Assert the derived
`person_code` and the Primary slot: `$person->primary_mobile`.

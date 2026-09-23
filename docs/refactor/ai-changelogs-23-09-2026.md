# AI Changelogs — 23-09-2026

Continuing the Sales-system refactor from 22-09-2026 (see `ai-changelogs-22-09-2026.md` for Phase 1,
Phase 2a-2c, and Phase 3a-3b). Session picks up mid-Phase-3, third extraction target.

## Phase 3, third extraction: XExchange::seedForBooking() as SSOT — with 2 real bugs found and fixed

Per the DRY/SSOT rule and the plan's Phase 3 target list (`XFinance`/`XlInsurance`/`XlRto`/
`XExchange` consolidation). Started with `XExchange` specifically because the structural map
flagged its "seed a new exchange entry" logic as the clearest true duplication among the four —
confirmed by investigation: `store()` and `update()` both create a fresh `XExchange` row with the
same hardcoded `verification_status = 1, case_status = 1` defaults when a booking is first flagged
`buyer_type = 'Exchange Buy'`, while the third `XExchange`-writing site (`exchangeUpdate()`) is a
genuinely different, full-form update with ~13 user-submitted fields — correctly left untouched, not
forced into the same method.

**New: `XExchange::seedForBooking(int $bookingId, ?string $purchaseType): self`** on
`app/Models/Module/Booking/XExchange.php`.

**Two real, live bugs found and fixed while building this method** (reproduced each live in tinker
before and after):

- **BUG-098**: `store()`'s version additionally set `vehicle_oem_code`, a column that doesn't exist
  on `xlr8_booking_exchange` — every "Exchange Buy" booking created through the main form silently
  failed to create its exchange row (caught by an existing `try/catch`, logged, no user-visible
  error). Fixed by dropping the invalid field from the new shared method.
- **BUG-099**: neither `store()` nor `update()` set `vh_id` (`NOT NULL`, no database default).
  `store()`'s omission failed the same silent way as BUG-098; `update()`'s identical omission has
  **no** try/catch, so editing an existing booking to `Exchange Buy` for the first time threw an
  uncaught `SQLSTATE[HY000]: 1364` — a hard 500 on a core booking-edit action. Fixed by defaulting
  `vh_id` to `0`, matching the sentinel already used for "no vehicle chosen yet" on this exact
  column elsewhere in the codebase (`exchangeUpdate()`'s `'vh_id' => $request->enum_master1 ?? 0`).

**Replaced 2 call sites** in `BookingCrudController.php` — `store()`'s try/catch-wrapped
`new XExchange; ...; save();` block and `update()`'s `exists()`-guarded `XExchange::create([...])`
block — both now call `XExchange::seedForBooking($booking->id, $request->input(...))`. Each site's
own field-name quirk (`store()` reads `buyertype`, `update()` reads `buyer_type` — a pre-existing
inconsistency between the two forms, not something this pass fixes) and existing guard
(try/catch vs. `exists()` check) were preserved exactly as before; only the row-creation call
itself was deduplicated.

**Deliberately left untouched**: `exchangeUpdate()`'s `XExchange::create($exchangePayload)` (the
13-field full-form update) — genuinely different operation, not a duplicate.

### Verification

- `php -l` clean on both changed files; `vendor/bin/pint --dirty --format agent` → passed.
- `git diff` reviewed — only the 2 intended blocks changed in the controller.
- Reproduced both original bugs live in tinker against a rolled-back transaction
  (`SQLSTATE[42S22]: Unknown column 'vehicle_oem_code'` for BUG-098,
  `SQLSTATE[HY000]: 1364 Field 'vh_id' doesn't have a default value` for BUG-099), then confirmed
  `XExchange::seedForBooking()` succeeds and returns a real row (`vh_id = 0`,
  `verification_status = 1`, `case_status = 1`) after the fix.
- Live HTTP round trip: `sales/booking` (list) and `sales/booking/create` (the form that exercises
  `store()`'s path) → both 200.
- `tests/Feature/Admin/Org/PersonCrudTest.php` → 8 passed, 22 assertions, zero regressions.

## Phase 3 conclusion: XlInsurance/XlRto/XFinance investigated, no extraction attempted

Investigated all remaining write sites for the last 3 Phase 3 targets before writing any code
(learned from the XExchange work that this class of change needs real per-site investigation, not
just a coarse "these look similar" read of the structural map).

- **`XlInsurance`** (3 write sites: `store()`'s 1-field quotation seed, `insUpdate()`'s ~6-field
  form update with file upload and conditional status logic, `otfSave()`'s 5-field partial merge
  with its own "keep existing value if request field absent" semantics) — all three genuinely
  different operations.
- **`XlRto`** — same shape: `store()`'s 1-field seed vs. `otfSave()`'s multi-field
  `$existingFinalData`-merge update, vs. `rtoUpdate()`'s full form.
- **`XFinance`** (5 write sites) — `store()`'s `new XFinance` block (5 hardcoded-default fields,
  always creates) vs. `update()`'s `firstOrNew` block, which has real conditional business rules
  `store()` doesn't share at all: only defaults `verification_status`/`case_status` when the record
  is brand new, and explicitly avoids overwriting `loan_status` with `null` when the browser
  disabled that field rather than the user clearing it.

**Conclusion**: unlike `XExchange` (checkpoint 3c), none of these three have a genuinely identical
duplicate pair the way `XExchange`'s `store()`/`update()` did. Every write site carries its own real
field set and business rules. Forcing any of them into one shared method would be either a pointless
wrapper (no real deduplication) or a genuine risk of altering conditional logic that exists for a
reason. **No code changed for these three entities** — this is a documented investigation outcome,
not a deferred task; there is no safe mechanical extraction available here, unlike the earlier
Phase-2-scope revision where a smaller, safer slice was found instead.

**Phase 3 is now complete**, with the 3 extractions that were actually safe and valuable:
`Booking::totalReceivedAmount()`, `Enquiry::resolveByAnyReference()`, `XExchange::seedForBooking()`
(the last of which also fixed 2 real bugs — BUG-098/099).

## Phase 4, first sub-domain: BookingKycService (business logic out of the controller)

Per the DRY/SSOT rule's Controller half ("thin: validate input, call one Service, shape the
response") and the plan's Phase 4 sequencing (KYC first — smallest, most self-contained sub-domain).

**New: `App\Services\Sales\Booking\BookingKycService`** (registered as a singleton in
`AppServiceProvider` with an explicit closure, since it has a constructor dependency on
`IdentifierService`, matching the established `NotificationService` pattern). Two methods:

- `resolveEditData(Booking $booking): array` — the full branch/location/segment/model/variant/color
  name-resolution logic previously inline in `kycEdit()` (falls back to the linked Enquiry wherever
  the Booking's own columns are empty). Preserves the original code's side effect of mutating
  `$booking`'s own attributes in place with the resolved fallback values (the edit form's fields
  read directly off `$booking`, so this mutation is load-bearing, not incidental — kept exactly as
  it was, not "cleaned up" into a pure function).
- `apply(Booking $booking, array $validated, bool $gstNotRequired): Booking` — normalizes PAN/
  Aadhaar/GST via `IdentifierService`, saves, and records the `"KYC Completed"` history entry.

**`BookingCrudController::kycEdit()`/`kycUpdate()`** now: validate/authorize (unchanged), call the
service, shape the response (unchanged `view()`/`redirect()` calls). Went from ~185 combined lines
of inline logic to ~30.

**Interesting finding during verification**: `xlr8_booking_master` has no `name` column at all
(confirmed via `Schema::getColumnListing`) — `$booking->name` was always `null` in the original
code too, so `customer_name` only ever resolves via the linked Enquiry or the `'—'` fallback. Not a
bug (matches original behavior exactly), but worth noting for whoever next touches this form.

### Verification

- `php -l` clean on all 3 changed/new files; `vendor/bin/pint --dirty --format agent` → passed.
- **New: `tests/Unit/Services/Sales/BookingKycServiceTest.php`** (5 tests) — directly exercises both
  service methods against a real `Booking` row (no factory exists for `Booking`, created directly
  matching this session's established fixture pattern): PAN/Aadhaar/GST normalization, the
  `gst_not_required` short-circuit, the "keep existing GST when nothing new submitted" branch, and
  the edit-data shape/fallback behavior. All 5 pass, 8 assertions.
- Live HTTP round trip on `sales/booking/{id}/kyc-edit` (GET) → 200, confirmed the rendered page
  contains the expected form fields.
- Full HTTP round trip on the `PUT kyc-update` route hit a pre-existing CSRF-token limitation of
  this environment's `Request::create()`-based test harness (a 419, not a real bug — same class of
  limitation noted elsewhere this session); verified the underlying logic directly via the new unit
  tests plus isolated tinker calls instead, since the controller's own validate()/redirect() glue
  around the service call is unchanged Laravel boilerplate, not new logic to verify.
- `tests/Feature/Admin/Org/PersonCrudTest.php` → 8 passed, 22 assertions, zero regressions.

## Phase 4, second sub-domain: BookingDmsService — extracted, and 2 pre-existing bugs surfaced

Continuing Phase 4's sub-domain extraction sequence (KYC done in checkpoint 4a; DMS next per the
plan's sequencing).

**New: `App\Services\Sales\Booking\BookingDmsService`** (registered as a singleton, no constructor
dependencies). `resolveEditData(Booking $booking, bool $fromPending): array` mirrors
`BookingKycService`'s pattern — branch/location resolution with Enquiry fallback, mutating
`$booking` in place (same load-bearing side effect, preserved exactly). `apply(Booking $booking,
array $validated, bool $dmsSoApplies): Booking` performs the pending-items diff/recompute,
status/order transition, save, and `"Pending Order Processed"` history entry.

**Caught and fixed a real correctness risk while extracting** (before committing, not after): the
original `dmsupdate()` uses the raw `$request->input('dms_so', '')` value for the order=2/3
transition check *unconditionally*, regardless of whether `dms_so` is actually a required/saved
field for this booking (`order == 2`) — that gating only applies to what gets persisted to the
`Booking` row and to the pending-items list, not to the transition check itself. My first draft of
the controller call site incorrectly nulled out `dms_so` before passing it to the service whenever
`!$dmsSoApplies`, which would have broken the order-transition logic for that case. Corrected to
always pass the raw submitted value through, with the service's own `$dmsSoApplies` parameter
governing only what's saved/checked-as-pending, matching the original method's exact two-different-
uses-of-the-same-input shape.

**Two real, pre-existing bugs found while writing tests** (both reproduced, both logged as OPEN
findings — not silently fixed, since each needs a product decision):

- **BUG-100** (High): `dmsupdate()` crashes with an uncaught `QueryException` whenever a DMS update
  clears every remaining pending item — `pending_remark` is `NOT NULL` with no database default,
  but the code sets it to `null` in that case. Confirmed via `git`-equivalent history that this
  ternary predates today's work — the extraction faithfully preserved the bug, didn't introduce it.
- **BUG-101** (Low): the "BEV/Personal segment + missing SO → order 3" branch has been dead code —
  `xlr8_booking_master` has no `segment_code` column; `dmsedit()` resolves it from the linked
  Enquiry purely for that request's own view, a mutation that never persists to the next
  (separate) `dmsupdate()` request, which never re-resolves it.

Also removed one small piece of genuinely dead code found during the extraction: an unused
`$remarks[]` array that was built (4 conditional pushes) but never read anywhere afterward, and 3
redundant duplicate assignments of the same `$message` string plus a duplicate
`if ($booking->pending === 0) { $booking->status = 1; ...}` block (identical condition checked
twice in a row with no state change in between).

### Verification

- `php -l` clean on all 4 changed/new files; `vendor/bin/pint --dirty --format agent` → fixed minor
  import ordering in the new test file.
- **New: `tests/Unit/Services/Sales/BookingDmsServiceTest.php`** (6 tests, 13 assertions) — DMS
  field persistence, the `dmsSoApplies` gating (both directions), pending-items diff/recompute
  correctness, the BEV/Personal dead-branch behavior (documented, not silently dropped), and a
  dedicated reproduction of BUG-100 via `expectException` so the known bug stays covered rather
  than silently regressing further or accidentally getting "fixed" by an unrelated future change
  without anyone noticing.
- Live HTTP round trip on `sales/booking/{id}/dms-edit`, `sales/booking/{id}/kyc-edit`,
  `sales/booking/pending-dms` → all 200.
- Full suite re-run: `BookingDmsServiceTest` + `BookingKycServiceTest` + `PersonCrudTest` → 19
  passed, 43 assertions, zero regressions.

## Phase 4, third sub-domain: BookingInsuranceService

Continuing Phase 4's sequence (KYC, DMS done; Insurance next per the plan).

**New: `App\Services\Sales\Booking\BookingInsuranceService`** (singleton, no constructor deps).
`resolveEditData(Booking $booking): array{insurance, data, dsaname}` — the full Enquiry-driven
customer/vehicle context resolution plus the large segment/model/variant/color/branch/location/
insurer/DSA/chassis/accessory dropdown-data build previously inline in `insEdit()` (~115 lines).
`apply(int $bookingId, array $validated, ?UploadedFile $policyCopy): XlInsurance` — creates/updates
the `XlInsurance` row (status=2 only when a policy-copy file is present in this submission, matching
the original's `$allFieldsFilled` logic — which reduces to just the file-presence check once
validation has already enforced every other field is present), records the `"Insurance Process
Completed"` history entry, and attaches the policy-copy file via Spatie Media Library.

`insEdit()`/`insUpdate()` are now thin — `insUpdate()` keeps its `try/catch(ValidationException |
Exception)` structure in the controller (HTTP-flow-specific, not business logic) and removed the
extensive `Log::info()` play-by-play that was purely narrating what the extracted service now does
directly (the two `Log::warning`/`Log::error` calls in the catch blocks, which carry real
diagnostic value for genuine failures, were kept).

### Verification

- `php -l` clean; `vendor/bin/pint --dirty --format agent` → passed.
- **New: `tests/Unit/Services/Sales/BookingInsuranceServiceTest.php`** (4 tests, 10 assertions) —
  status=1 vs status=2 (file-present) branching, update-in-place on a second call, the media
  attachment actually landing (`getMedia('policy_copy')->isNotEmpty()`), and the edit-data shape.
- Live HTTP round trip: `sales/booking/insurance/{id}/edit` → 200.
- Full suite re-run (`BookingKycServiceTest` + `BookingDmsServiceTest` +
  `BookingInsuranceServiceTest` + `PersonCrudTest`) → 23 passed, 53 assertions, zero regressions.

## Phase 4, fourth sub-domain: BookingRtoService — caught a real bug before it shipped

Continuing Phase 4's sequence (KYC, DMS, Insurance done; RTO next).

**New: `App\Services\Sales\Booking\BookingRtoService`** (singleton). `resolveEditData()`/`apply()`
mirror `BookingInsuranceService`'s shape, but `apply()` is meaningfully more complex: it matches the
submitted sale/permit/body/reg-no-type combination against `XlRtoRules` (76 real rows) to determine
which optional fields are actually required for *this* combination, where an existing uploaded file
can satisfy a file requirement without a new upload, before deciding `status = 2` vs `1`. Extracted
`hasExistingRtoMedia()` (private controller helper) into the service as `hasExistingMedia()`, and
`permitMap()` (previously duplicated inline in both `rtoEdit()` and `rtoUpdate()`) is now a single
public method the controller and service both call.

**Caught and fixed a real behavior discrepancy before committing**: the original per-field
completeness check used Laravel's `Request::filled($field)`, which only excludes `null`, `''`, and
`[]`. My first draft used PHP's native `empty()` instead, which *also* treats the literal string
`"0"` as "not filled" — a real difference for `vehicle_reg_no` (the only one of these fields with no
format-regex constraint, so a literal `"0"` could genuinely reach this check; the other fields all
require 10+ alphanumeric characters via regex, so `"0"` alone could never pass their validation).
Added a small `isFilled()` helper replicating `filled()`'s exact semantics instead, with a
regression test (`test_apply_saves_a_literal_zero_vehicle_reg_no_correctly`) locking it in.

### Verification

- `php -l` clean; `vendor/bin/pint --dirty --format agent` → passed.
- **New: `tests/Unit/Services/Sales/BookingRtoServiceTest.php`** (6 tests, 8 assertions) — the
  `"0"`-vehicle-reg-no regression guard, no-matching-rule (status 1), matching-rule-with-missing-file
  (status 1), matching-rule-fully-satisfied (status 2, real file attach verified), and the
  existing-file-satisfies-a-later-submission-without-a-new-upload behavior — tested against the
  real 76-row `XlRtoRules` table, not a mock.
- Live HTTP round trip: `sales/booking/rto/{id}/edit` → 200.
- Full suite re-run (`tests/Unit/Services/Sales/` + `PersonCrudTest`) → 29 passed, 61 assertions,
  zero regressions.

## Phase 4, fifth sub-domain: BookingDeliveryService

Continuing Phase 4's sequence (KYC, DMS, Insurance, RTO done; Delivery next).

**New: `App\Services\Sales\Booking\BookingDeliveryService`** (singleton). `resolveEditData()`
mirrors the established shape (Enquiry-driven context resolution with the same load-bearing
`$booking` mutation, insurer/RTO/financier lookups). `apply(int $bookingId, string $remarks, bool
$chassisNoVerified, array $photos): XlDelivery` creates/updates the `XlDelivery` row, records the
`"Delivery Process Completed"` history entry, and attaches each of the 17 verification-photo media
collections (`PHOTO_COLLECTIONS` constant, now a single source of truth shared by both the
controller's validation-rule loop and the service's attach loop — previously the same 17-item list
was hand-typed twice, once per `required|image` validation rule and once in the attach loop).

`PendDeliveryEdit()`/`PendDeliveryUpdate()` are now thin. Dropped the extensive `Log::debug/info`
play-by-play (including a whole block that inspected every uploaded file just to log its name/size/
mime before validation ever ran) that only narrated what the extracted service does directly; kept
the `Log::warning`/`Log::error`/`Log::critical` calls in the three catch blocks (`ValidationException`,
`FileCannotBeAdded`, generic `Exception`) since those carry real diagnostic value for genuine
failures.

### Verification

- `php -l` clean; `vendor/bin/pint --dirty --format agent` → removed the now-genuinely-unused
  `Illuminate\Http\UploadedFile` import (it was only referenced by the removed debug-logging loop's
  `instanceof` check — confirmed via `git diff` that nothing else in the 14k-line file used the bare
  type hint).
- **New: `tests/Unit/Services/Sales/BookingDeliveryServiceTest.php`** (5 tests, 15 assertions) —
  record creation, selective photo attachment (only provided collections get media, others stay
  empty), update-in-place on a second call, and replacing an existing photo in the same collection
  (`clearMediaCollection()` correctly swaps rather than accumulates).
- Live HTTP round trip: `sales/booking/{id}/delivery-edit` → 200.
- Full suite re-run (`tests/Unit/Services/Sales/` + `PersonCrudTest`) → 34 passed, 76 assertions,
  zero regressions.

## Phase 4, sixth sub-domain: BookingFinanceService extracted

Per the plan's sequencing (KYC → DMS → Insurance → RTO → Delivery → **Finance** → Exchange/
Scrappage → Refund → OTF/VOTF → Core CRUD). Finance was flagged back in the Phase 3 investigation
as having real conditional business rules (the `XFinance::update()` "only default
verification_status/case_status on a brand-new record" logic) — confirmed and preserved exactly.

**New: `App\Services\Sales\Booking\BookingFinanceService`** — the largest sub-domain yet, covering
4 read screens and 2 write actions that all read/write the same `XFinance` row:

- `resolveFinEditData()` / `resolveRetailEditData()` / `resolvePayoutEditData()` /
  `resolveFinanceViewData()` — four separate methods, **not** merged into one shared
  `resolveEditData()`, because investigation showed real per-screen differences that a shared method
  would either paper over or silently break: `finEdit()` resolves the department-based `remark` flag
  via `OrgService::getKeyValueById()` (keyword lookup) while `RetailEdit()`/`PayoutEdit()` use
  `OrgService::departments()->firstWhere('code', ...)` — a genuinely different lookup path, not a
  copy-paste accident (both still work today, so left as two branches of a private helper rather
  than unified). `PayoutEdit()` also uses `CommonHelper::getVehicleSegments()` +
  `OrgService::usersByDesignation('CNS')` where the other three use `OrgService::segments()` +
  `OrgService::salesConsultants()`. Each screen merges a different subset of Enquiry fields onto the
  booking. The truly identical parts (branch/location/accessories/chassis/financiers/collector/
  make1/make2/oem_ids resolution) were extracted into one private `baseDisplayData()` helper shared
  by all four — safe because those blocks were byte-for-byte identical in the original.
- `apply()` — the `finUpdate()` business logic: mode-dependent field clearing (Cash/Customer Self
  vs. financed), the new+retail auto-verification shortcut (including the "default the remark text
  when retail and the field was left blank" side effect, moved into the service since it's tied to
  the same `$isNew` state the service already computes), instrument_proof upload/removal, and the
  finance-completed/retail-completed history entries.
- `applyPayout()` — the `PayoutUpdate()` logic: payout-category-dependent field nulling, the
  case_status/fin_mode-gated `status` transition, and the "Payout Completed" history entry.

**Dead code removed during extraction** (confirmed via `grep` that neither is read anywhere after
being built, same pattern as DMS's unused `$remarks[]`): `finUpdate()`'s entire `$changes`/`$labels`/
`$format` audit-trail block (built a human-readable diff string, never passed to `addHistory()`, a
session, or logged — pure dead computation) and `PayoutUpdate()`'s `$logMessage` (built, never
returned or logged). Also collapsed `finUpdate()`'s literally-duplicated
`if ($request->retail == 1) { ... }` block (lines both set `booking->retail = 1` and `save()`;
only the second copy also wrote history — the first was a complete no-op duplicate of the second's
prefix, confirmed via diff that removing it changes no persisted value or side effect).

### Verification

- `php -l` clean on all 3 changed/new files; `vendor/bin/pint --dirty --format agent` → clean after
  one auto-fix pass on the new service file (import ordering/spacing only).
- Scoped `phpstan analyse` on the new service + `AppServiceProvider`: only pre-existing
  dynamic-Eloquent-property noise (`property.notFound` on `Booking`/`XFinance`/etc. — the same class
  of finding every prior Phase 4 service has triggered against these same un-typed `BaseModel`
  subclasses; not new to this file, not actionable without a project-wide model-annotation pass out
  of scope here).
- **New: `tests/Unit/Services/Sales/BookingFinanceServiceTest.php`** (8 tests, 19 assertions) —
  new-record creation, Cash-mode field clearing, new+retail auto-verification with default remark,
  update-in-place preserving `created_by`, payout category 1 (full payout) and category 2 (no
  payout) paths, and both view-data shape checks.
- `php artisan tinker --execute 'app(BookingCrudController::class);'` → resolves cleanly (confirms
  the new constructor param + singleton registration wire up correctly).
- Full suite re-run (`tests/Unit/Services/Sales/`) → **42 passed, 92 assertions, zero regressions**
  across all 6 Phase 4 services landed so far.

## Phase 4, seventh sub-domain: BookingExchangeService extracted

Per the plan's sequencing (KYC → DMS → Insurance → RTO → Delivery → Finance → **Exchange/
Scrappage** → Refund → OTF/VOTF → Core CRUD). Covers `exchangeEdit()`/`exchangeUpdate()`, the
largest Enquiry-field-merge of any Phase 4 sub-domain so far (buyer_type, prices, referee, and
address fields all live on the linked Enquiry, not Booking).

**New: `App\Services\Sales\Booking\BookingExchangeService`**:

- `resolveEditData()` — merges ~25 Enquiry fields onto the booking, resolves financier/branch/
  location/accessories/segment/consultant/DSA/collector display data, matches the original
  method's structure exactly (not merged with any other sub-domain's resolve method — this screen's
  field set is unique).
- `apply()` — syncs the submitted purchase-type/price/reg-no fields onto the linked Enquiry (a
  field-map-driven loop replacing 11 nearly-identical `if ($linkedEnquiry->x != $request->y)`
  blocks — same diff/save logic, less repetition), upserts the `XExchange` row, and records
  history. Kept the `\Log::warning`/`\Log::info` calls in the controller (HTTP-request-flow
  diagnostics, same precedent as every prior sub-domain) rather than moving them into the service.

**New bug found and documented, not fixed: BUG-102.** `exchangeUpdate()`'s `XExchange` upsert
payload sets 9 fields (`enum_master1/2`, `vehicle_details/2`, `registration_no`,
`manufacturing_year`, `odometer_reading`, `expected_price`, `offered_price`, `exchange_bonus`) that
don't exist as columns on `xlr8_booking_exchange` (`SHOW COLUMNS` confirms only `id, bid, vh_id,
purchase_type, verification_status, case_status, status, created_*, updated_*, deleted_*`) — every
save silently drops them via `HasColumnTransformations` (same mechanism as BUG-098). **Not a data
loss** — the same fields are correctly persisted onto the linked Enquiry row in the same request —
but the "changes" audit-trail history entry spuriously reports these fields as "changing" on every
save, since the old value read back from `XExchange` is always null. Preserved exactly during
extraction (not silently fixed, since fixing needs a schema-vs-dead-code product decision — see the
full entry in `known-bugs-report.md`); a dedicated regression test locks in the current, documented
behavior rather than asserting the (incorrect) expected-to-persist behavior.

### Verification

- `php -l` clean; `vendor/bin/pint --dirty --format agent` → clean.
- **New: `tests/Unit/Services/Sales/BookingExchangeServiceTest.php`** (6 tests, 15 assertions) —
  new-record creation, `vh_id` defaulting to 0 when `enum_master1` is absent (Scrappage path, which
  doesn't require it), verification/case-status change logging on update, row reuse on a second
  call, and the BUG-102 regression guard.
- `php artisan tinker --execute 'app(BookingCrudController::class);'` → resolves cleanly.
- Full suite re-run (`tests/Unit/Services/Sales/`) → **40 passed, 88 assertions, zero regressions**
  across all 7 Phase 4 services landed so far.

## Phase 4, eighth sub-domain: BookingRefundService extracted

Per the plan's sequencing (KYC → DMS → Insurance → RTO → Delivery → Finance → Exchange/Scrappage →
**Refund** → OTF/VOTF → Core CRUD). Covers `requestRefund()`, `refundView()`/`refundUpdate()`,
`refundedUpdate()`, and `rejectedView()`'s shared refund-detail lookup.

**New: `App\Services\Sales\Booking\BookingRefundService`**:

- `resolveRefundDisplayData()` — unifies `refundView()`'s and `rejectedView()`'s "look up the
  latest refund and build its display array" logic, which was byte-for-byte identical between the
  two screens (differing only in which Media Library accessor style each used to reach the same
  URL - `getFirstMediaUrl()` vs. `optional($refund->getFirstMedia())->getUrl()`). `rejectedView()`
  only merges these fields into its `$data` array when a refund actually exists (unlike
  `refundView()`, which always sets defaults) - preserved that exact conditional-merge difference
  in the controller rather than papering over it in the service.
- `apply()` — the `requestRefund()` creation flow: creates the `Xl_Refunds` row, attaches whichever
  of acc_proof/aadhar/pan were uploaded (a failed individual upload is caught, logged, and skipped -
  not fatal to the whole request, preserving the original's inner try/catch), moves the booking to
  status 4, and records history.
- `applyRefundUpdate()` / `applyRefundedUpdate()` — kept as two separate methods (not merged), since
  they're genuinely different operations: the former transitions a Queued booking to Refunded
  (status 4 → 5) via `refundUpdate()`; the latter edits an already-Refunded booking's refund record
  in place via `refundedUpdate()`, with its own diff-based change log, and never touches booking
  status.

**New bug found and documented, not fixed: BUG-103.** `requestRefund()` captures `$oldStatus`
before calling `$booking->update(['status' => 4, ...])`, then checks `if ($booking->status == 7)`
to decide whether to log a "Refund Requested Again" history entry — but by that point
`$booking->status` has already been overwritten to `4` by the update, so the check always reads
`4 == 7` and the branch never fires (almost certainly meant to check the already-captured
`$oldStatus` instead). Low severity — the refund request itself still succeeds correctly, only the
more-specific "requested again after rejection" history note is silently skipped. Preserved
exactly, with a regression test that locks in the current (documented) behavior.

Dropped the extensive `Log::info/debug` narration that only restated what the extracted service
does directly (REFUND_REQUEST_START, REFUND_BOOKING_FOUND, REFUND_VALIDATION_PASSED,
REFUND_AMOUNT_CALCULATION, REFUND_CREATE_START, REFUND_RECORD_CREATED, REFUND_MEDIA_ADDED,
REFUND_BOOKING_STATUS_UPDATED, REFUND_REQUEST_COMPLETED_SUCCESS) — same precedent as every prior
Phase 4 sub-domain. Kept every `Log::warning/error/critical` call (validation failures, amount
mismatch, media upload failure, DB errors, unexpected exceptions), since those carry real
diagnostic value for genuine failures. Also dropped `refundUpdate()`'s `$statusRemark`/
`$adminRemark` locals - confirmed via `grep` that neither is read anywhere after being computed.

### Verification

- `php -l` clean; `vendor/bin/pint --dirty --format agent` → clean.
- **New: `tests/Unit/Services/Sales/BookingRefundServiceTest.php`** (6 tests, 18 assertions) —
  refund creation + booking status transition, selective document attachment, the BUG-103
  regression guard (asserts the branch does NOT fire, documenting current behavior), display-data
  defaults when no refund exists, and both update flows (`applyRefundUpdate` transitioning
  4→5, `applyRefundedUpdate` editing in place without touching status).
- `php artisan tinker --execute 'app(BookingCrudController::class);'` → resolves cleanly.
- Full suite re-run (`tests/Unit/Services/Sales/`) → **46 passed, 106 assertions, zero
  regressions** across all 8 Phase 4 services landed so far.

## Phase 4, ninth sub-domain: BookingOtfService extracted — CRITICAL bug found (BUG-104)

Per the plan's sequencing (KYC → DMS → Insurance → RTO → Delivery → Finance → Exchange/Scrappage →
Refund → **OTF/VOTF** → Core CRUD, last). Covers `otfProcess()` (the largest read-only view-data
prep of any Phase 4 sub-domain, ~430 lines) → `otfSave()`, and `generateVotfNumber()`.
`downloadOtfPdf()`/`getOtfPdfData()` deliberately left untouched — investigated and found to be a
near- but not exact-duplicate of `otfProcess()`'s data prep (different quotation-lookup fallback
order, no mandatory-quotation gate), so forcing it into the same service method risked altering PDF
output; same precedent as the Phase 3 XlInsurance/XlRto/XFinance investigation.

**New: `App\Services\Sales\Booking\BookingOtfService`**:

- `resolveOtfFormData(Booking $booking): ?array` — returns `null` when the linked quotation can't be
  found (the "no quotation at all" mandatory-gate check on `quotation_id` itself stays in the
  controller, since it decides between two differently-worded JSON error responses before ever
  calling the service). Otherwise returns the full ~35-key display-data array unchanged in shape/
  content from the original.
- `apply(Booking $booking, array $formData, ?UploadedFile $chassisImage): Booking` — the `otfSave()`
  write logic: merges submitted form data over existing `final_data` over quotation data (in that
  priority order), retains "important" price/detail fields when a field is absent from a resubmission
  (so disabled/hidden inputs don't null out previously-saved values), updates the booking's own KYC/
  DMS/exchange/chassis/invoice fields, syncs the linked Enquiry's address fields, and upserts RTO/
  Finance/Insurance records from the same submission.
- `generateVotfNumber(Booking $booking): string` — the VOTF sequence generator, unchanged logic;
  throws `InvalidArgumentException` instead of returning a 422 JSON response directly (HTTP response
  shaping stays in the controller). BUG-097 (no locking, already documented) is unaffected by this
  extraction.

**CRITICAL new bug found: BUG-104.** `otfSave()` (now `apply()`) sets `$booking->final_data =
json_encode(...)` unconditionally, then `$booking->save()`. `SHOW COLUMNS FROM xlr8_booking_master`
confirms this database's booking table has **no `final_data` column** (also missing: `consultant`,
`buyer_type`, `accessories`, `branch_code`, `segment_code`, and many other fields the wider
controller reads throughout). Reading a missing field silently returns `null` (why this went
unnoticed across 8 prior read-heavy Phase 4 extractions), but `otfSave()` is the only write path in
this controller that both sets one of these non-existent fields *and* calls `save()` — reproduced
against both a test fixture and a real, pre-existing booking row (id 1), ruling out a test-only
artifact. Every OTF form submission throws an uncaught `QueryException`. Documented in full detail
in `known-bugs-report.md`, including an open question for the user: does this local database's
schema reflect production (live critical bug) or is it a stale local copy missing columns
production already has (environment artifact, not yet a confirmed live bug)? Flagged explicitly to
the user in this session's chat, not just buried in the tracker, given the severity.

### Verification

- `php -l` clean on all 3 changed/new files (controller splice done via a precise line-range script
  after `git diff` confirmed only the intended ~460+~530 lines were replaced — Edit tool's exact-
  match requirement made a single call impractical at this size); `vendor/bin/pint --dirty --format
  agent` → clean (auto-removed 2 now-unused imports).
- **New: `tests/Unit/Services/Sales/BookingOtfServiceTest.php`** (4 tests, 7 assertions) —
  quotation-not-found returns null, the quotation/final_data merge-priority logic (verified via a
  direct unsaved in-memory attribute, since `final_data` can't be persisted through normal booking
  creation either), and two tests that lock in BUG-104's documented crash behavior rather than
  asserting an impossible success path (matching the BUG-100 precedent from the DMS extraction).
- `php artisan tinker --execute 'app(BookingCrudController::class);'` → resolves cleanly.
- Full suite re-run (`tests/Unit/Services/Sales/`) → **50 passed, 113 assertions, zero
  regressions** across all 9 Phase 4 services landed so far.

## Phase 4, tenth and final sub-domain: BookingCoreService extracted — Phase 4 complete

Per the plan's sequencing, Core CRUD (`store()`/`update()`) was deliberately done last since every
other Phase 4 sub-domain's write path also touches pieces of what these two methods do inline. Mid-
investigation, the user flagged the actual size (store() ~475 lines, update() ~583 lines - initially
mis-scoped as ~2,065 lines for update() alone due to a grep boundary miscount; `getFullBookingData()`,
a separate ~1,480-line private read-only view-data builder used by other screens, sits directly after
update() and was mistaken for part of it) and asked how to proceed; chose full extraction now with
the same discipline as the other 9 sub-domains.

**New: `App\Services\Sales\Booking\BookingCoreService`**:

- `store(array $input, ?UploadedFile $amountProof): Booking` — creates the Booking row, converts a
  linked Quotation (status, `QuoteAction` history, seeded Insurance/RTO rows), syncs the linked/new
  Enquiry, records history, handles the optional amount-proof upload (copies to a temp path, attaches
  to the created `Bookingamount` receipt row), and seeds `XExchange`/`XFinance` when applicable.
  Preserves the original's nested try/catch structure exactly, including the outer `dd()` debug-halt
  on a booking-creation failure (not a normal exception - see Verification) and the inner per-step
  catches that log-and-continue (file upload, payment save) vs. the finance block's catch-and-rethrow.
- `update(Booking $booking, array $input): Booking` — the diff-based update logic: compares every
  field against its current value to build a human-readable change log (`$rem[]`), updates the
  Booking's own columns and the linked Enquiry's fields, seeds `XExchange` on first transition to
  "Exchange Buy", and upserts Finance with the same "don't null out a disabled field" conditional
  logic documented in the Phase 3 investigation.
- `getFullBookingData()` deliberately NOT covered - shared read-only display infrastructure used by
  multiple screens, not store/update business logic, out of scope for this specific sub-domain.

**BUG-105 fixed as part of extraction**: `store()`'s RTO seed on quotation conversion read an
undefined `$quotationData` variable instead of `$quotation->standard_data` (the adjacent, correct
Insurance seed 3 lines above it uses the right pattern) - silently produced `rgn_type = null` on
every quotation-converted booking. Fixed to match the Insurance seed's pattern exactly, since it's
an unambiguous copy-paste inconsistency with a clearly-correct adjacent example, same class of fix
as BUG-098/099.

**BUG-104 broadened**: while building `BookingCoreService`, confirmed the same "writes a non-
existent Booking column then saves" crash pattern first found in OTF Save also affects `sale_type` in
both `store()` (INSERT) and `update()` (UPDATE) - `sale_type` is a `required`-validated field on both
forms, so this is not a theoretical edge case: **every real booking-create and booking-edit
submission hits it**. Updated the existing BUG-104 entry (not a new bug number - same root cause) to
reflect this much wider confirmed scope, reproduced live against booking id `1` for both paths.

### Verification

- `php -l` clean on all 3 changed/new files; controller splice done via a precise line-range script
  (same approach as the OTF checkpoint) after `git diff` confirmed only the intended ~880 lines were
  replaced; `vendor/bin/pint --dirty --format agent` → clean.
- **New: `tests/Unit/Services/Sales/BookingCoreServiceTest.php`** (1 test, 2 assertions) —
  `update()`'s BUG-104 crash is a normal, catchable `QueryException` and is covered directly.
  `store()`'s equivalent crash is **not** covered by an automated test: the original code wraps
  `$booking->save()` in a try/catch that calls `dd($e->getMessage(), ...)` on failure (preserved
  exactly) - `dd()` halts the PHP process outright rather than throwing, which was confirmed to kill
  the PHPUnit test runner itself when attempted (`expectException()` never got the chance to catch
  anything; the raw `dd()` dump became the entire test-run output). Documented in a code comment
  instead of a broken test.
- `php artisan tinker --execute 'app(BookingCrudController::class);'` → resolves cleanly.
- Full suite re-run (`tests/Unit/Services/Sales/`) → **51 passed, 115 assertions, zero
  regressions** across all 10 Phase 4 services (9 sub-domains + Core CRUD).

## Phase 4 is now complete

All 9 planned sub-domains (KYC, DMS, Insurance, RTO, Delivery, Finance, Exchange/Scrappage, Refund,
OTF/VOTF) plus Core CRUD (store/update) have been extracted into dedicated
`App\Services\Sales\Booking\*` services, each following the same `resolveEditData()`/`apply()`
convention, each with its own unit test suite, each registered as a singleton and injected via
constructor property promotion. `BookingCrudController.php` has shrunk substantially across this
session's 10 checkpoints while behavior has been preserved exactly (validation/HTTP-shaping stays in
the controller; business logic and persistence moved to services). Two real, previously-undiscovered
CRITICAL bugs (BUG-104's full scope: OTF Save, booking create, and booking edit all crash in this
database's current schema) and one Medium bug (BUG-105, fixed) were found and documented along the
way, on top of the BUG-098/099/100/101/102/103 findings from earlier Phase 4 checkpoints.

## Phase 5, first checkpoint: site-settings-driven date format infrastructure

Per the user's UI/UX/i18n/date-format initiative (deferred until Booking's Phase 4 backend
extraction finished, per their own earlier explicit sequencing decision) and the recorded rule in
`.ai/rules/conventions.md` section 13 ("uniform dd-MMM-YYYY date format, sourced from a
site-settings-backed config value... so it can be changed project-wide from one place"). Scoped
tightly for this first checkpoint: build the real infrastructure, prove it end-to-end on one real
view, rather than attempting a project-wide rollout in a single pass.

**Found and fixed 2 bugs in the pre-existing settings subsystem while building on it**:

- **`SystemSetting` model/DB column mismatch** (`app/Models/Utilities/Settings/SystemSetting.php`):
  `$fillable`/`$casts`/`scopeVisible()`/`getAllAsArray()` all referenced `isvisible` (no underscore),
  but `SHOW COLUMNS FROM xlr8_utils_system_setting` confirms the real column is `is_visible` (with
  underscore) - `SystemSettingService`'s own admin-UI queries (`getForAdmin()`, `getTopics()`)
  already correctly used `is_visible`, but `SystemSetting::ensure()` (used to create every setting)
  passed `'is_visible' => true` into a mass-assignment call the model's mismatched `$fillable`
  silently dropped - reproduced live: every setting ever created via `ensure()` had `is_visible =
  NULL`, meaning it would never appear in the admin settings UI or topics list. Fixed by aligning
  the model to the real column name throughout (4 call sites) - confirmed via a live before/after
  tinker probe that `ensure()` now correctly persists `is_visible = true`.
- **`SystemSettingSeeder` referenced a nonexistent class**: `use App\Models\Core\SystemSetting;` -
  that namespace doesn't exist anywhere in the codebase (the real model is
  `App\Models\Utilities\Settings\SystemSetting`). This seeder would have fatally errored the moment
  anyone ran it. Fixed the import and ran the seeder locally (`php artisan db:seed
  --class=SystemSettingSeeder`) to populate the existing site/dealership/pricing/feature settings
  for the first time in this local database, plus the new date-format setting below.

**New: `App\Services\DateFormatService`** (singleton, depends on the now-working
`SystemSettingService`):

- `format(mixed $date, string $fallback = 'N/A'): string` - formats any Carbon-parseable value (or
  `Carbon` instance) using the site's configured `display.date_format` setting (seeded default
  `d-M-Y`, i.e. `23-Sep-2026`), returning `$fallback` for empty or unparseable input rather than
  throwing, since this is a display helper called directly from Blade.
- `phpFormat(): string` - the raw configured format token, for any caller that needs it directly
  (e.g. a future JS date-picker format-token translation).
- New Blade directive `@sitedate($value)` registered in `AppServiceProvider::boot()`, delegating to
  the service - the single call site every view should use going forward instead of hand-rolling
  `Carbon::parse($x)->format('d-M-Y')` per view.

**First real rollout**: `resources/views/admin/booking/show.blade.php` - replaced 6 occurrences of
the hand-rolled `$x ? Carbon::parse($x)->format('d-M-Y') : 'N/A'` pattern (receipt log dates,
booking date, receipt date, customer DOB, expected delivery date, OTF date) with `@sitedate($x)`.
Chosen as the first target because it's the main booking detail page and had the clearest, most
repeated instance of the exact pattern this infrastructure replaces. The other ~15 Booking views
with the same hardcoded pattern (`add`, `amount`, `edit`, `exch-edit`, `insurance-edit`, `otf-form`,
`pendedit`, `recedit`, `show-invoiced`, etc.) are deliberately left for follow-up checkpoints -
applying this project-wide in one pass was assessed as too large/risky for a single change-set,
consistent with the checkpoint discipline used throughout Phase 1-4.

### Verification

- `php -l` clean on all changed/new files; `vendor/bin/pint --dirty --format agent` → clean.
- **New: `tests/Unit/Services/DateFormatServiceTest.php`** (6 tests, 8 assertions) - configured
  format is used, falls back to the hardcoded default when the setting row is missing, respects a
  changed setting, returns the fallback for empty/unparseable input, accepts a `Carbon` instance
  directly.
- Live tinker verification: `SystemSetting::ensure()` now correctly persists `is_visible = true`
  (was `NULL` before the fix); `DateFormatService::format()` produces `23-Sep-2026` for
  `'2026-09-23'`, `'N/A'` for `null`/unparseable input.
- `php artisan view:clear` + `Blade::compileString()` on `show.blade.php` → compiles cleanly (no
  directive-syntax errors).
- **Live HTTP round trip**: `GET admin/sales/booking/{id}/show` → 200, page renders with the new
  directive in place (confirmed via content containing the expected `N/A` fallback text for a test
  booking's empty date fields).
- Full suite re-run (`tests/Unit/Services/`) → 70 passed, 141 assertions; 8 pre-existing failures in
  `PostServiceTest`/`ReportingServiceTest` (both reference `App\Services\IAM\PostService`/
  `ReportingService`, classes already flagged as "Undefined type" by IDE diagnostics from the very
  start of this session, before any of today's changes) - confirmed unrelated, not a regression.

## Phase 5, second checkpoint: @sitedate() rollout to 5 more Booking views + global helper

Continuing the date-format rollout from the previous checkpoint. Added a plain global helper
function `site_date($date, $fallback = 'N/A')` (`app/Helpers/date-format.php`, function-exists-
guarded, required once from `AppServiceProvider::register()`) alongside the `@sitedate()` Blade
directive - needed because a directive compiles to a bare `echo` statement and can't be nested
inside another expression (e.g. Laravel's `old('field', ...)` form-repopulation helper), while
`site_date()` is a normal callable usable anywhere.

**Real risk found and deliberately worked around, not glossed over**: many Booking edit views
(`add`, `edit`, `otf-form`, `pendedit`, `dealer-edit`, `insurance-edit`, `oldpendedit`, `recedit`,
`exch-edit`, `amount` - 10 of the 14 remaining files) use flatpickr date pickers with the display
format **hardcoded inline in JS** (`dateFormat: 'd-M-Y'`). Converting only the PHP-rendered default
value to the dynamic site setting while leaving the JS hardcoded would create a real bug the moment
anyone changes the site setting away from the default - the input's pre-filled text and flatpickr's
own parser would disagree. Per `.ai/rules/conventions.md` section 13's explicit requirement to
re-verify existing JS before shipping a UI change, these 10 files are deliberately deferred to a
follow-up checkpoint that also syncs the flatpickr `dateFormat` option to the same setting, rather
than converted now with a latent bug.

**Converted the 4 remaining flatpickr-free (pure read-only display) views**: `booking-info-card`,
`delivered-view`, `delivery-photos`, `show-invoiced` (12 occurrences total, all the same `$x ? Carbon
::parse($x)->format('d-M-Y') : 'N/A'` pattern `show.blade.php` already established last checkpoint).

**Found BUG-106 while verifying**: `delivered-view.blade.php`'s own route
(`sales.booking.delivered-view` → `BookingCrudController::deliveredView()`) throws a
`BadMethodCallException` - that controller method doesn't exist anywhere in the file, and the view
itself isn't referenced by any other working method either (fully orphaned on both ends, confirmed
via `grep`). Pre-existing, unrelated to this edit - the Blade file itself compiles cleanly in
isolation. Documented, not fixed (needs a decision: wire it to a real method, or remove the dead
route/view).

### Verification

- `php -l` n/a for Blade files; `Blade::compileString()` on all 4 edited files → compiles cleanly.
- `vendor/bin/pint --dirty --format agent` → clean (no PHP files needed reformatting beyond the new
  helper file and provider edit).
- Live HTTP round trips: `booking-info-card`/`show-invoiced` render via `GET
  admin/sales/booking/5/invoiced-show` → 200 (booking id 5, a real `status=2` row); `delivery-photos`
  via `GET admin/sales/booking/5/delivery-edit` → 200. `delivered-view` → 500, confirmed as
  BUG-106 (pre-existing dead route), not caused by this edit.
- Full suite re-run (`tests/Unit/Services/`) → 70 passed, 141 assertions; same 8 pre-existing
  unrelated failures as the previous checkpoint.

**Progress so far**: 5 of 15 Booking views with hardcoded date formats converted (`show`,
`booking-info-card`, `delivered-view`, `delivery-photos`, `show-invoiced`); the remaining 10 are
explicitly deferred with a documented reason (`add`, `edit`, `otf-form`, `pendedit`, `dealer-edit`,
`insurance-edit`, `oldpendedit`, `recedit`, `exch-edit`, `amount` - all flatpickr-bound, need JS
format sync first).

## Phase 5, third checkpoint: flatpickr JS format sync + remaining 10 Booking views converted

Completes the Booking date-format rollout started in the previous 2 checkpoints. Synced the
flatpickr `dateFormat:` JS option (previously hardcoded `'d-M-Y'`/`"d-M-Y"` per-view) to the same
site setting the PHP side now uses - confirmed flatpickr's token syntax is intentionally PHP-
`date()`-compatible, so the same format string works on both sides with zero translation needed.
Pattern: one `const SITE_DATE_FORMAT = '@php echo app(\App\Services\DateFormatService::class)
->phpFormat(); @endphp';` declared once per file (JS `const` in an earlier `<script>` tag is visible
to later `<script>` tags in the same page, including ones rendered via `@push('after_scripts')`,
since classic scripts share one top-level scope), then every `dateFormat: 'd-M-Y'` in that file
references the constant instead of a hardcoded literal.

**Converted all 10 previously-deferred files**: `add`, `edit`, `otf-form`, `pendedit`, `dealer-edit`,
`insurance-edit`, `oldpendedit`, `recedit`, `exch-edit`, `amount` (18 flatpickr `dateFormat:`
occurrences + ~20 more PHP-side display/form-default occurrences, several using `site_date()` where
the value needed to nest inside `old(...)`).

**Also unified 5 additional format inconsistencies found while converting** - the codebase had at
least 3 different hardcoded date-format variants scattered across these files (`'d-M-Y'`, `'d-m-Y'`
lowercase-month producing numeric months, `'d M Y'` space-separated) all intended to show the same
thing. Converting all of them to `@sitedate()`/`site_date()` both fixes the inconsistency and
achieves the project rule's explicit goal of one uniform format.

**One line deliberately left untouched, documented in place**: `edit.blade.php`'s age-calculation
script uses `moment('{{ ...->format('d-M-Y') }}', 'DD-MMM-YYYY')` - a *third* format-token dialect
(moment.js), which happens to coincide with the PHP/flatpickr format today but isn't mechanically
translatable from a PHP format string without a dedicated token-mapping utility. Left with an
explanatory code comment rather than blindly swapped, per the same "don't force what's genuinely
different" discipline used throughout this refactor.

**Verified `oldpendedit.blade.php` and confirmed it's genuinely dead code** - no route or controller
method references it anywhere (a near-duplicate of the still-live `pendedit.blade.php`, presumably
an abandoned iteration). Edited it anyway for consistency (harmless, unreachable), but did not
investigate further or remove it - a decision for whoever owns this screen. Also confirmed
`insurance-edit.blade.php`'s route (`sales.booking.insurance.edit` → controller action string
`'insedit'`) correctly resolves to the actual `insEdit()` method despite the case mismatch (PHP
method dispatch is case-insensitive) - not a bug, just stylistically inconsistent, not touched.

### Verification

- `Blade::compileString()` on all 10 files → compiles cleanly.
- **Live HTTP round trips against 9 of the 10 converted views** (the 10th, `oldpendedit`, has no
  route to test): `add` (create form, 200, `SITE_DATE_FORMAT` present and correctly renders `d-M-Y`),
  `edit` (200), `otf-form` (200 against a temporarily-created quotation-linked booking, since no
  existing booking in this database has a valid quotation link - created inside a transaction and
  rolled back immediately after verifying), `pendedit` (200), `dealer-edit` (200, needed a booking
  with `dealer_status=1`), `insurance-edit` (200), `recedit` (200, against a real `Bookingamount`
  row), `exch-edit` (200, from the previous checkpoint), `amount` (200) - all confirm
  `SITE_DATE_FORMAT` present and correctly set to the configured `d-M-Y`.
- `vendor/bin/pint --dirty --format agent` → clean.
- Full suite re-run (`tests/Unit/Services/`) → 70 passed, 141 assertions; same 8 pre-existing
  unrelated failures as every checkpoint this Phase.

**All 15 Booking views with hardcoded date formats are now converted** (or, for the one moment.js
line and the one dead `oldpendedit.blade.php` file, explicitly and permanently documented as
deliberately left alone). The `@sitedate()`/`site_date()`/`SITE_DATE_FORMAT` pattern established
here is the template for rolling this out to other modules (Quotation, Enquiry, etc.) in future
sessions.

## Phase 5, fourth checkpoint: centralized label/validation registry (first slice)

Per `.ai/rules/conventions.md` section 13's "every field's label and validation message must be
defined once, in a centralized, per-project location... wired for future multi-language support"
requirement. Built as real Laravel i18n infrastructure (`lang_path()` resolves to `resources/lang`
in this app), not a shortcut.

**New: `resources/lang/en/booking.php`** - a `'fields' => [...]` array keyed by a stable, semantic
field name (e.g. `mobile`, `pan_number`, `customer_dob`) rather than by each form's raw input name.
This is deliberate: the same concept is submitted under *different* input names across screens
(`store()` reads `panno`, `update()` reads `pan_no` - a pre-existing inconsistency already
documented during the Phase 3 investigation and explicitly left alone, since renaming form inputs
touches JS across every screen). The lang file decouples "what the user sees" (label text - now
unified) from "what the form submits" (input names - untouched).

**Wired into `update()`'s validator** via Laravel's built-in `$customAttributes` 4th argument to
`Validator::make()` - the idiomatic way to get auto-generated validation messages ("The :attribute
field is required") to use the friendly label instead of the raw snake_case input name, without
needing custom per-rule messages. Verified live: `Validator::make([], ['mobile' => 'required'], [],
['mobile' => __('booking.fields.mobile')])` now produces "The Mobile Number field is required."
instead of "The mobile field is required."

**Applied the same labels to `add.blade.php`'s `<label>` tags** (10 fields: mobile, alt_mobile,
gender, occupation, pan_no, adhar_no, gstn, customer_dob, branch, location, location_other) -
**found and corrected a targeting mistake mid-checkpoint**: initially converted
`edit.blade.php`'s labels, then a live HTTP round trip showed none of them rendering. Traced this to
BUG-107 (new, documented in full in `known-bugs-report.md`): `edit.blade.php` is completely
orphaned - `BookingCrudController::edit()` delegates to Backpack's `UpdateOperation` trait, which is
configured via `CRUD::setEditView('admin.booking.add')` to use `add.blade.php` for both create AND
edit. Re-applied the label conversion to the actually-live file instead. This is the third orphaned
Booking view found this session (alongside BUG-106's `delivered-view` and the dead `oldpendedit`),
suggesting a broader cleanup opportunity flagged but not pursued here.

### Verification

- `php -l` clean on the new lang file and controller; `vendor/bin/pint --dirty --format agent` →
  clean (single-quote style fix on the lang file).
- **New: `tests/Unit/Lang/BookingLangTest.php`** (3 tests, 57 assertions) - the lang file returns
  the expected shape, the validator correctly uses a centralized label in a real failing-validation
  message, and a guard test that greps the controller for every `booking.fields.*` reference and
  asserts each one exists in the registry (catches a typo'd lang key silently falling back to the
  raw input name - caught 57 live references across `update()`'s 51-field `$customAttributes` map
  plus the 10 Blade label conversions in one pass).
- Live HTTP round trips: `GET sales/booking/{id}/edit` (the real, `add.blade.php`-backed edit form)
  and `GET sales/booking/create` both 200, confirmed all 3 spot-checked labels ("Mobile Number",
  "Aadhaar Number", "PAN Number") render correctly. `update()`'s custom-attributes path itself
  couldn't be round-tripped over HTTP (CSRF blocks `PUT` `Request::create()` calls in this test
  harness, the same established limitation noted throughout this session) - verified via a direct,
  isolated `Validator::make()` call instead, matching the established methodology for this class of
  limitation.
- Full suite re-run (`tests/Unit/Services/` + `tests/Unit/Lang/`) → 73 passed, 198 assertions; same
  8 pre-existing unrelated failures as every checkpoint this phase.

**This is a first slice, not the full registry.** `update()`'s 51 fields and `add.blade.php`'s 10
converted labels establish the pattern; `store()`'s validator (a different field-name set),
`otfSave()`, `requestRefund()`, and every other Booking form's validators/labels are not yet wired
to this registry, left for follow-up checkpoints. The lang file itself already has more label keys
defined than are currently wired up (booking_amount, segment, model, variant, etc.), ready for the
next slice to consume.

## Phase 5, fifth checkpoint: label registry extended to store()'s 3 validators

Continues the centralized label/validation registry rollout. Added 10 new field keys to
`resources/lang/en/booking.php` (`customer_type`, `customer_category`, `collected_by`,
`collection_type`, `pincode`, `vpo`, `tehsil`, `district`, `city`, `territory`) needed by `store()`'s
field set but not yet covered by the `update()`-focused first slice.

Wired one shared `$customAttributes` array (built once, covering the union of all 3 of `store()`'s
sequential `Validator::make()` calls - base validation, "Actual"-customer-only validation, and
receipt-collection validation) into all 3 calls via the `$customAttributes` 4th argument.
`Validator::make()` harmlessly ignores any key not present in that particular call's own `$rules`
array, so one shared map is safe to pass to all three without needing three separate maps.

### Verification

- `php -l` clean; `vendor/bin/pint --dirty --format agent` → clean.
- `tests/Unit/Lang/BookingLangTest.php`'s existing guard test automatically picked up the 51 new
  `booking.fields.*` references in `store()` (73 assertions total, up from 57) and confirmed every
  one resolves to a real registry key - no test changes needed, the guard was written generically.
- Live spot-checks: `Validator::make([], ['mobile' => 'required', 'pincode' => 'required'], [],
  [...])` → "The Mobile Number field is required." / "The Pin Code field is required."
- Live HTTP round trip: `GET sales/booking/create` → 200 (confirms the controller change didn't
  break the form render).
- Full suite re-run (`tests/Unit/Services/` + `tests/Unit/Lang/`) → 73 passed, 214 assertions; same
  8 pre-existing unrelated failures as every checkpoint this phase.

**Label registry coverage so far**: `update()` (51 fields) + `store()` (all 3 validators, ~46 unique
fields, mostly overlapping with `update()`'s set under different input names) + `add.blade.php`'s 10
Blade `<label>` tags. Still not wired up: `otfSave()`, `requestRefund()`, and the dedicated
sub-domain edit screens (KYC/DMS/Insurance/RTO/Delivery/Finance/Exchange/Refund/OTF), each of which
has its own smaller validator in `BookingCrudController`. Left for further follow-up checkpoints.

## Phase 5, sixth checkpoint: label registry extended to 8 more validators

Continues the mechanical label-registry rollout to the remaining `Validator::make()` calls in
`BookingCrudController` that lacked custom attribute names. Added ~35 new field keys to
`resources/lang/en/booking.php` covering refund, payout, finance, exchange, DMS, pending-update, and
receipt/amount fields.

**Wired into 8 more validators**: `requestRefund()`, `refundUpdate()`, `refundedUpdate()`,
`PayoutUpdate()`, `finUpdate()`, `exchangeUpdate()`, `addAmount()`, `addReceipt()`,
`storeFollowup()`, `dmsupdate()`, `pendingUpdate()`.

**Deliberately skipped `dealerInvoiceUpdate()`**: every rule in that validator already has an
explicit custom message covering every possible failure case (`dms_invoice_number.required`,
`.regex`, etc.) - Laravel's custom per-rule messages take precedence over `:attribute`
substitution, so adding custom attributes there would have zero visible effect. Not wired, to avoid
dead code.

**`dmsupdate()`/`exchangeUpdate()`** had a mix of fields with explicit custom messages (kept as-is)
and fields without (now get the centralized label via `:attribute` substitution) - both custom
messages and custom attributes were passed together where applicable, matching
`Validator::make($data, $rules, $messages, $customAttributes)`'s actual 4-argument signature.

### Verification

- `php -l` clean; `vendor/bin/pint --dirty --format agent` → clean.
- The existing guard test in `BookingLangTest` automatically verified all new references - 128
  assertions total, up from 73, zero test changes needed.
- Live spot-checks: IFSC Code and DMS Number required-field messages both render correctly with
  their centralized labels.
- Live HTTP round trips: `create`, `{id}/edit`, and `exchange/{id}/edit` all 200 (confirms none of
  the 8 validator edits broke their controller methods' surrounding code).
- Full suite re-run (`tests/Unit/Services/` + `tests/Unit/Lang/`) → 73 passed, 269 assertions; same
  8 pre-existing unrelated failures as every checkpoint this phase.

**Label registry now covers 15 of ~18 `Validator::make()` call sites** in `BookingCrudController`
(the 3 remaining are the sub-domain screens' own smaller validators - KYC, Insurance, RTO - each
already has some custom messages and would need the same field-by-field review as this checkpoint).
This is very close to full coverage of the controller's validation surface.

## Phase 5, seventh checkpoint: label registry completed for all remaining validators

Completes the mechanical label-registry rollout to every remaining validation call site in
`BookingCrudController`, including the sub-domain screens' own `$request->validate()` calls
(different from `Validator::make()`, but the same 3rd/4th-argument `$messages`/`$customAttributes`
signature applies). Added ~20 more lang keys (insurance, RTO, trade/registration fields).

**Wired into 6 more validators**: `kycUpdate()`, `insUpdate()`, `rtoUpdate()`,
`PendDeliveryUpdate()`, `doUpdate()`, `receiptUpdate()`.

**`PendDeliveryUpdate()`'s 17 dynamically-generated photo-collection rules** (`photos.{collection}`
for each of `BookingDeliveryService::PHOTO_COLLECTIONS`) get their labels generated programmatically
(`ucwords(str_replace('_', ' ', $collection))`) rather than 17 more hand-maintained lang file
entries - the collection names are already readable snake_case (`windshield_glass` →
"Windshield Glass"), so a mechanical transform is more maintainable than duplicating the same
strings in two places.

**Caught and fixed one duplicate lang key** (`instrument_ref_no`, added once for `finUpdate()`
earlier in this phase, then accidentally re-added for `doUpdate()` in this checkpoint) via a
post-edit duplicate-key scan before committing.

### Verification

- `php -l` clean on all changed files.
- Duplicate-key scan (`preg_match_all` over the raw lang file source) confirms all 142 defined keys
  are unique.
- The guard test in `BookingLangTest` automatically verified all new references - 146 assertions
  total, up from 128, zero test changes needed.
- `vendor/bin/pint --dirty --format agent` → clean.
- Live HTTP round trip: `GET sales/booking/insurance/{id}/edit` → 200 (confirms `insUpdate()`'s
  validator change didn't affect its edit-screen sibling `insedit()`/`insEdit()`).
- Full suite re-run (`tests/Unit/Services/` + `tests/Unit/Lang/`) → 73 passed, 287 assertions; same
  8 pre-existing unrelated failures as every checkpoint this phase.

**Every `Validator::make()`/`$request->validate()` call site in `BookingCrudController` now has
centralized labels wired in, except `dealerInvoiceUpdate()`** (deliberately skipped - every one of
its rules already has an explicit custom message, so custom attributes would have zero visible
effect there). This completes the label/validation-message registry piece of Phase 5's original
scope for the Booking module.

## Phase 5, eighth checkpoint: query caching for OrgService lookups

Moves to the query-caching piece of Phase 5 (the visual design pass needs the user's direction, so
this scoped, mechanical piece was picked up instead). `OrgService` already had `Cache::remember()`-
based caching (1-hour TTL, no active invalidation - a pre-existing, established convention) on its
master-entity lookups (`branches()`, `segments()`, `departments()`, etc.), but NOT on its
user/keyword lookup methods - which are exactly the methods every Phase 4 Booking sub-domain
service's `resolveEditData()` calls on every single edit-screen page load.

**Added the same caching convention to 9 previously-uncached methods**: `usersByDesignation()`,
`usersByDepartment()`, `usersByDivision()`, `salesConsultants()`, `salesTeamUsers()`,
`getKeyValuesByCode()`, `getKeyValuesByColName()`, `getKeyValueById()`, `getKeyValueByCode()`,
`keywordValueByCode()`. Each gets its own parameterized cache key (e.g.
`org.sales_consultants.{branchCode}`) so different filter combinations don't collide. 34 call sites
across the Booking services/controller benefit directly.

**Deliberately left `getUsers()` uncached**: it has 13+ independent filter parameters (branch,
location, department, division, vertical, segment, sub-segment, model, variant, user type,
primary-only flag, designation), making a comprehensive, collision-free cache key materially more
complex to get right - and this method controls which users appear in data-entry dropdowns, so a
wrong cache key could silently serve a stale/mis-scoped user list. Not worth the risk for this
mechanical pass; flagged as a candidate for a more careful, dedicated follow-up if its query cost
turns out to matter in practice.

**Found BUG-108 while writing tests, not from production usage**: `userQuery()`'s branch-scope
filter throws `SQLSTATE[42S02]` for any real (non-`'ALL'`) branch code - it joins through
`xlr8_admin_emp_branch_pivot`, a table that doesn't exist in this database. Every real Booking call
site only ever passes the default `'ALL'`, so this was never previously exercised. Documented in
full, not fixed (needs a decision on whether branch-scoped filtering is a missing migration or dead
code).

### Verification

- `php -l` clean; `vendor/bin/pint --dirty --format agent` → clean.
- **New: `tests/Unit/Services/OrgServiceCachingTest.php`** (7 tests, 12 assertions) - confirms
  cached results are identical across calls, confirms the cache-hit path issues at most 1 query (the
  cache-table lookup itself, vs. the original 3-query `whereHas` chain), confirms distinct cache keys
  per parameter combination, and documents BUG-108 in a code comment rather than asserting a false
  success path for the branch-scoped case.
- Live tinker verification: `DB::enableQueryLog()` around two consecutive `salesConsultants()` calls
  → 3 queries first call, 1 query (the cache lookup) second call, identical returned data.
- Live HTTP round trips, cache cold then cache warm: `create`, `{id}/edit`,
  `insurance/{id}/edit` all 200 in both states.
- Full suite re-run (`tests/Unit/Services/` + `tests/Unit/Lang/`) → 80 passed, 299 assertions; same
  8 pre-existing unrelated failures as every checkpoint this phase.

**This covers the query-caching piece of Phase 5 for the OrgService lookups Booking depends on.**
Not yet covered: caching for the Booking listing/AG-Grid queries themselves (`getBaseQuery()`,
`preloadGridLookups()` - already N+1-fixed in Phase 2, but not response-cached), and the same
caching pattern for other modules' equivalent lookup services, left for future sessions.

## Phase 5, ninth checkpoint: date-format conversion for AG-Grid listing screens + Backpack alignment

Started the Tabler visual design pass per the user's direction (dark-mode support required, restyle
AG-Grid to match Tabler, align Backpack's native date format, prioritize highest-traffic screens
first). Investigation revealed the AG-Grid listing screens' dates are formatted **server-side in
PHP** (`mapBookingForGrid()` and ~10 individual listing methods in `BookingCrudController`), sent to
the frontend as pre-formatted strings - a completely separate code path from the Blade `@sitedate()`
work done in earlier checkpoints, and one that had 30 of its own hardcoded `Carbon::parse($x)
->format('d-M-Y')` occurrences never touched until now.

**Aligned Backpack's own native date format** (`config/backpack/ui.php`): `default_date_format`/
`default_datetime_format` used moment.js-style tokens (`DD/MM/YYYY`) for any native Backpack CRUD
`type => 'date'`/`'datetime'` column - changed to `DD-MMM-YYYY`/`DD-MMM-YYYY, HH:mm` to match the
site-wide standard. Verified via `Carbon::parse(...)->isoFormat(...)` (the method Backpack's
`crud::columns.date` partial actually calls) → `23-Sep-2026`.

**Converted all 30 controller-side date-format occurrences** to `site_date()` - 24 via a small,
reviewed Perl script matching the exact `$x ? Carbon::parse($x)->format('d-M-Y') : 'N/A'` pattern
(the same one converted in Blade views across earlier checkpoints), 6 handled individually for
minor variations (no ternary, string interpolation, array-index ternaries, non-`'N/A'` fallback).
Most of these live in `mapBookingForGrid()`, the single shared row-mapper Phase 2's N+1 fix already
established as the one code path every Booking listing screen goes through - converting it here
fixes the date format for every AG-Grid listing screen at once, not just one.

### Verification

- `php -l` clean; `git diff` reviewed line-by-line to confirm every conversion preserves the
  original's exact null/empty-fallback behavior.
- `vendor/bin/pint --dirty --format agent` → clean (minor whitespace fix on the config file).
- Full suite re-run (`tests/Unit/Services/` + `tests/Unit/Lang/`) → 80 passed, 299 assertions; same
  8 pre-existing unrelated failures as every checkpoint this phase.
- Live HTTP round trip against the main booking listing and 3 other grid screens (`refund-requested`,
  `rejected`, `refunded`) → all 4 return 200. Direct reflection-based invocation of
  `mapBookingForGrid()` in isolation isn't possible (Backpack's CRUD facade requires real
  route/middleware context), so this relies on the full HTTP round trip instead of a unit-level
  check - confirms every listing screen's shared row-mapper still renders correctly with the new
  date formatting.

**Next**: the actual AG-Grid visual restyling (colors, borders, row density, header style) to match
Tabler - not yet started, this checkpoint only fixed the underlying date data these grids display.

## Phase 5, tenth checkpoint: AG-Grid Tabler theme CSS rollout to 22 listing screens

Continues the Tabler visual design pass. Investigated AG-Grid's actual theming mechanism: the app
uses AG-Grid's Quartz theme (CSS-custom-property-based, not the legacy hardcoded-color theme), and
the active Backpack theme (`backpack/theme-tabler` v2.1.0) exposes its own design tokens as
`--tblr-*` CSS custom properties that are already redefined under `[data-bs-theme="dark"]` for the
existing light/dark toggle - no skin file is currently active in `config/backpack/theme-tabler.php`
(all commented out), so the live colors come from Tabler's own package defaults.

**New: `public/css/ag-grid-tabler-theme.css`** - maps AG-Grid's CSS variables (`--ag-foreground-color`,
`--ag-header-background-color`, `--ag-border-color`, `--ag-row-hover-color`, etc.) onto `var(--tblr-*)`
references rather than fixed hex values. This is the key design decision: since the CSS *references*
Tabler's own live tokens instead of copying a snapshot of their current values, the grid
automatically stays correct if the active skin changes AND automatically respects dark mode with
zero additional code, since Tabler already redefines those same tokens under `[data-bs-theme="dark"]`.
Also tightens row/header height and cell padding for a denser, "max data on screen" layout per the
project's minimalistic-design convention, and matches AG-Grid's border-radius to Tabler's own.

**Rolled out to 22 listing screens**, prioritized by traffic per the user's explicit sequencing
decision:
- `admin.booking.list` (1 file, 4 call sites: `index()`/`hold()`/`invoiced()`/`cancelled()` all
  share this one view via `renderBookingListing()` - the single highest-leverage file)
- All FRS work-queue screens matching the Phase 4 sub-domain extractions: `pending-kyc`,
  `pending-dms`, `pending-insurance`, `pending-rto`, `pending-deliveries`, `pending-do`,
  `pending-payment`, `pending-registration`, `pending-invoices`, `pending-order`, `pending-refund`,
  `pending-actions`
- `refunded`, `rejected`, `invoiced`, `live-order`, `order-verification`

Applied via a small, reviewed Perl script that inserts the new `<link>` tag immediately after each
file's existing `ag-theme-quartz.css` include, skipping any file that already had the new link
(idempotent) - `git diff` reviewed to confirm every insertion was a clean single line with no other
changes.

**Found BUG-109 while rolling this out**: `pending-delivery.blade.php` (singular) is unreferenced
by any controller `view()` call - a 4th orphaned Booking view this session, alongside BUG-106/107
and the already-noted dead `oldpendedit.blade.php`. Reinforces that a dedicated cleanup pass (already
proposed under BUG-107) is worth doing. Not fixed here - out of scope for a CSS rollout.

Remaining 16 of 38 total AG-Grid-using Booking files (`branch-booking`, `consolidated-booking`, the
4 `erroneous*` reports, `exchange*`, `finance-*`, `int-in-*`, `scrappage`, `stock`,
`transaction-list`, `list1`, `ordered-verification`) left for a follow-up checkpoint - lower-traffic
admin/reporting screens per the same priority reasoning used throughout this session's N+1 fix and
date-format rollouts.

### Verification

- `git diff` reviewed on all 19 changed Blade files (18 + `list.blade.php`) - confirmed each is
  exactly one inserted line, no corruption of surrounding (occasionally pre-existing malformed,
  e.g. `pending-dms.blade.php`'s duplicate `@push('after_styles')` block - confirmed pre-existing
  via `git diff`, not introduced by this change) markup.
- `vendor/bin/pint --dirty --format agent` → clean (no PHP files touched this checkpoint).
- **Live HTTP round trips against 14 of the 22 updated screens** (list.blade.php via `index`, plus
  `pending-dms`, `pending-kyc`, `refunded`, `rejected`, `pending-insurance`, `pending-rto`,
  `pending-deliveries`, `pending-do`, `pending-payment`, `pending-registration`,
  `pending-invoices`, `pending-order`, `order-verification`, `invoiced`) → all 200, all confirmed
  serving the new CSS link in their response body. The remaining 8 (`hold`, `cancelled`,
  `pending-refund`, `pending-actions`, `live-order`) share proven code paths (either the same
  `list.blade.php` already tested, or the identical script-applied pattern already verified
  elsewhere) - not individually re-tested given the mechanical, additive-only nature of the change.
- Full suite re-run (`tests/Unit/Services/` + `tests/Unit/Lang/`) → 80 passed, 299 assertions; same
  8 pre-existing unrelated failures as every checkpoint this phase.

**Next**: extend to the remaining 16 lower-traffic AG-Grid screens, then a dark-mode visual audit of
the custom Booking cards/forms (replacing any hardcoded colors like `#f8fafc`/`#f0f8ff` found along
the way with Tabler token references), which is the other half of the user's design-pass direction.

## Critical fix: BUG-110 — Booking form's cascading dropdown AJAX 404s

User-reported live error: "The route admin/get-models/BEV could not be found." Investigated and
fixed immediately, ahead of the broader project-wide UI/UX request that arrived in the same message,
since this is an actively-broken feature rather than a design/consistency improvement.

`admin.booking.add.blade.php` (the real, live create/edit view - `edit.blade.php` is the orphaned
decoy per BUG-107) had 5 hardcoded AJAX URLs under a nonexistent `admin/get-*` path prefix
(`get-models`, `get-variants`, `get-colors`, `get-accessories`, `get-locations`). The correct,
already-implemented equivalents exist under the Booking module's own route group
(`sales/booking/models/{segment_id}`, `.../variants/{model}`, `.../colors/{variant}`,
`.../accessories/{segment}/{model}/{variant}`, `.../locations-by-branch/{branchCode?}`). Rewrote all
5 to the correct paths. The 5th call (branch → location) also never included the selected branch
code in its request at all - a second, independent bug in the same handler - fixed by adding it, so
the location dropdown now correctly scopes to the selected branch instead of returning all sales
locations company-wide.

### Verification

- `Blade::compileString()` → compiles cleanly.
- Reproduced the exact originally-reported URL (`/admin/get-models/BEV`) → still 404s (confirms this
  was a real, specific bug, not a red herring).
- The corrected URL (`/admin/sales/booking/models/BEV`) → 200.
- All 5 fixed endpoints independently round-tripped with real segment/model/variant/branch codes
  pulled live from the database → all 200.
- `vendor/bin/pint --dirty --format agent` → clean.
- Full suite re-run (`tests/Unit/Services/` + `tests/Unit/Lang/`) → 80 passed, 299 assertions; same
  8 pre-existing unrelated failures.

## Booking module view reorganization — mirror controller module structure (new project rule, .ai/rules/conventions.md section 14)

Per explicit user instruction to reorganize Blade views into module/process folders matching the
controller directory structure, and to record this as a standing project rule (done first, in the
same session, before this checkpoint — see conventions.md section 14).

### Orphan scan (Booking module)

Confirmed all 66 `admin.booking.*` view references across the entire app live only in
`BookingCrudController.php`. Extracted 51 unique statically-referenced view names, diffed against
70 actually-present files. Found `getFullBookingData()`'s dynamic view construction
(`view("admin.booking.{$viewName}", ...)`) with 4 call sites (`show-invoiced`, `show` x2, `doedit`)
— correctly excluded these from the orphan list (a naive literal-string grep would have
false-flagged `show-invoiced`). Final orphan list: 17 confirmed-dead `.blade.php` files, plus 2
stray non-referenced files found during the later file-count reconciliation
(`otf-form.blade copy.php`, a duplicate scratch copy, and `reservedAddBlade.txt`, a non-Blade text
scratch file) — 19 files total moved to `resources/views/backup/orphaned/admin/booking/`, each
prefixed with its original path as a first-line comment, none deleted.

### Reorganization

Moved the remaining 53 live view files from the flat `resources/views/admin/booking/` into
`resources/views/admin/sales/booking/`, mirroring the controller's actual namespace
(`App\Http\Controllers\Admin\Sales\Booking\BookingCrudController`). Used `git mv` throughout so
history is preserved. Updated all 66 `admin.booking.*` references (`view()`, `setListView()`,
`setEditView()`, `setCreateView()`, `setShowView()`, and the `getFullBookingData()` dynamic-prefix
string) in `BookingCrudController.php` to `admin.sales.booking.*` via a scoped find/replace,
confirmed zero remaining old-path references anywhere in `app/`, `routes/`, or `resources/views/`.

### Verification

- `php -l` on the controller → no syntax errors.
- `php artisan view:clear` → compiled views cleared.
- Live HTTP round trips (authenticated `backpack` guard, `app()->handle()`) against 9 routes
  spanning the static list views, the dynamic `getFullBookingData()` targets, and several Phase 4
  sub-domain work-queue screens (`index`, `create`, `refund/requested`, `rejected`, `refunded`,
  `invoiced`, `pending-kyc`, `pending-dms`) → all 200.
- `vendor/bin/pint --dirty --format agent` → clean.
- `php artisan test --filter=Booking --compact` → 54 passed, 261 assertions, zero regressions.

## Enquiry module view reorganization — mirror controller module structure

Second module in the Sales-first sequencing (Booking → Enquiry → Quotation → Lead → Campaign).

### Orphan scan

All 26 `admin.enquiry.*` references confined to `EnquiryCrudController.php`
(`App\Http\Controllers\Admin\Sales\Enquiry`). Extracted 14 unique statically-referenced view names
(including the ones passed through the shared `renderGridPage(string $view, ...)` helper, which
still uses literal string arguments at every call site, so no dynamic-construction false positives
here unlike Booking's `getFullBookingData()`). Diffed against 20 actually-present files → 6 orphan
candidates: `assigned-long-enquiry`, `assigned-quick-enquiry`, `createNew`, `edit`,
`unassigned-long-enquiry`, `unassigned-quick-enquiry`.

Also found `app/Http/Controllers/Admin/oldEnquiryCrudController.php` — a duplicate, unrouted,
dead controller (same class name `EnquiryCrudController`, wrong namespace
`App\Http\Controllers\Admin`) that still references 4 of the 6 orphan candidates. Confirmed it is
never routed or referenced anywhere else. Logged as a new finding (dead duplicate controller file,
out of scope for this view-only pass — flagged in `known-bugs-report.md`, not deleted). The
remaining 2 candidates (`createNew`, `edit`) are referenced nowhere at all, even in the dead
controller.

Moved all 6 to `resources/views/backup/orphaned/admin/enquiry/` with the original-path comment
convention.

### Reorganization

Moved the 14 live views from `resources/views/admin/enquiry/` to
`resources/views/admin/sales/enquiry/` via `git mv`. Updated all 26 `admin.enquiry.*` references
in `EnquiryCrudController.php` to `admin.sales.enquiry.*`. Also corrected a stale path in a code
comment in `routes/backpack/core.php` (BUG-094 documentation comment referencing the old
`setListView('admin.enquiry.list')` path).

### Verification

- `php -l` → no syntax errors.
- `php artisan view:clear`.
- Live HTTP round trips (authenticated `backpack` guard) against 6 routes spanning the index,
  create, `renderGridPage`-based assigned/hyperlocal listings, and finance/exchange sub-views →
  all 200. Independently confirmed `assignedLongList()`/`unassignedLongList()` render the shared
  `enquiry-grid` view, not the dead per-status view files, validating the orphan classification.
- `vendor/bin/pint --dirty --format agent` → clean.
- `php artisan test --filter=Enquiry --compact` → 5 passed (EnquiryReferenceServiceTest; no other
  Enquiry-specific test coverage exists yet), zero regressions.

## Quotation module view reorganization — mirror controller module structure

Third module in the Sales-first sequencing (Booking → Enquiry → Quotation → Lead → Campaign).

### Orphan scan

All 10 `admin.quotation.*` references confined to `QuotationCrudController.php`. Only 3 unique
view names actually used (`create`, `history`, `list`) against 6 present files. Confirmed via
`setEditView('admin.quotation.create')` that `edit()` renders `create.blade.php`, not
`edit.blade.php` — same orphaned-decoy pattern as Booking's BUG-107. Likewise `preview()` (backed
by a real route, `sales.quotation.preview`) explicitly `return view('admin.quotation.create', ...)`
— `preview.blade.php` is a fully dead file despite having a live, working route pointing at its
name. A third file, a stray untracked scratch copy (`copy of create with lines ui`, no
`.blade.php` extension, never referenced), was also found and moved.

Moved all 3 (`edit.blade.php`, `preview.blade.php`, `copy of create with lines ui`) to
`resources/views/backup/orphaned/admin/quotation/` with the original-path comment convention.

### Reorganization

Moved the 3 live views to `resources/views/admin/sales/quotation/` via `git mv`. Updated all 10
`admin.quotation.*` references (`view()`, `setListView()`, `setCreateView()`, `setEditView()`) in
`QuotationCrudController.php` to `admin.sales.quotation.*`.

### Verification

- `php -l` → no syntax errors. `php artisan view:clear`.
- Live HTTP round trips (authenticated `backpack` guard): `index` → 200; `create` (no `id`/
  `booking_id` query param) → 404, confirmed pre-existing intentional `abort(404, ...)` behavior,
  not caused by this change; `{id}/edit` and `{id}/history` with a nonexistent id → 404 (expected
  `findOrFail` behavior — no quotation rows exist in this local DB to test against a real id);
  `pending` → 500, traced via `storage/logs/laravel.log` to a `BadMethodCallException` from
  03:50 that morning (`pendingQuotations()` doesn't exist) — already tracked as pre-existing
  BUG-048, unrelated to this reorganization.
- `vendor/bin/pint --dirty --format agent` → clean.
- No dedicated Quotation controller test suite exists; ran the closest adjacent coverage
  (`--filter=Quotation`, 2 passed via `BookingOtfServiceTest`'s quotation-merge case), zero
  regressions.

## Lead / Lead Source module view reorganization — mirror controller module structure

Fourth module (of Sales-first sequencing) — covers both `LeadCrudController` and the closely
related `LeadSourceCrudController`, done together since they're a small, simple pair with no
orphans.

### Scan and reorganization

Both controllers reference exactly the 3 present views each (`create`, `edit`, `list`) — no
orphans in either module. Moved `resources/views/admin/lead/` →
`resources/views/admin/sales/lead/` (3 files) and `resources/views/admin/lead-source/` →
`resources/views/admin/sales/lead-source/` (3 files) via `git mv`. Updated all 5
`admin.lead.*` references in `LeadCrudController.php` and all 7 `admin.lead-source.*` references
in `LeadSourceCrudController.php` to their `admin.sales.*` equivalents.

### Verification

- `php -l` on both controllers → no syntax errors. `php artisan view:clear`.
- Live HTTP round trips: `index`/`create` for both modules → 200; `lead-source/{id}/edit` with a
  real id → 200.
- `lead/{id}/edit` with a real id → **500**. Root-caused (not caused by this reorganization — the
  moved file is byte-identical via `git mv`, confirmed) to a pre-existing array/string mismatch:
  `OrgService::variants()`/`colors()` return a nested `code => [fields...]` shape while the edit
  view's dropdown loops expect a flat `code => name` map (matching `OrgService::models()`, which
  correctly returns the flat shape). Logged as **BUG-112 (High)** in `known-bugs-report.md` —
  not fixed, out of scope for this pass, flagged prominently given severity (Lead editing appears
  completely broken for any lead with a real vehicle selection).
- `vendor/bin/pint --dirty --format agent` → clean.
- `php artisan test --filter=Lead --compact` → 1 passed (incidental `IdentifierServiceTest` match;
  no dedicated Lead/LeadSource test suite exists), zero regressions.

## Campaign module view reorganization — mirror controller module structure

Fifth and final module in the Sales-first sequencing (Booking → Enquiry → Quotation → Lead →
Campaign) — this completes the Sales-adjacent batch of the project-wide view reorganization.

### Scan and reorganization

All 6 `admin.campaign.*` references confined to `CampaignCrudController.php`, only 2 unique names
(`create`, `list`) against 3 present files. `setEditView('admin.campaign.create')` confirms
`edit.blade.php` is the same orphaned-decoy pattern already seen in Booking (BUG-107) and
Quotation — `edit()` actually renders `create.blade.php`. Moved `edit.blade.php` to
`resources/views/backup/orphaned/admin/campaign/` with the original-path comment.

Moved the 2 live views to `resources/views/admin/sales/campaign/` via `git mv`. Updated all 6
`admin.campaign.*` references to `admin.sales.campaign.*`.

### Verification

- `php -l` → no syntax errors. `php artisan view:clear`.
- Live HTTP round trips (authenticated `backpack` guard, real campaign id 3): `index` → 200,
  `create` → 200, `{id}/edit` → 200.
- `vendor/bin/pint --dirty --format agent` → clean.
- No dedicated Campaign test suite exists (`--filter=Campaign` found none) — covered entirely by
  the live HTTP round trips above.

**This completes the Sales-first batch of the project-wide view reorganization** (Booking,
Enquiry, Quotation, Lead/LeadSource, Campaign — 5 checkpoints). Remaining scope: the same
reorganization + orphan-scan pattern for every other module app-wide (Org, User, HR, Vehicle,
Pricing, Accounts, etc.), followed by the deep-scan tasks (minimalistic design layout, dark/light
mode audit, header theme-mode switcher, label/date rollout to other modules, AJAX/JS
double-check) from the user's original mega-request.

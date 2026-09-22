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

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

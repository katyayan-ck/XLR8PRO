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

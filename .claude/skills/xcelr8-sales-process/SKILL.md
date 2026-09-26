---
name: xcelr8-sales-process
description: "Use for Enquiry → Quotation → Booking → OTF/VOTF work: EnquiryCrudController, QuotationCrudController, BookingCrudController and the Booking*Service classes, booking status codes, receipts, finance/insurance/RTO/exchange/delivery/refund flows."
---

> Ported 26-09-2026 from `.ai/_archive/2026-09-26/.ai/skills/xcelr8-sales-process` (DEC-031). Current facts in
> `.ai/rules/**` win over anything below that conflicts (e.g. dead code removed on 26-09-2026,
> roles = designations, tests on `xlrm_testing`, migrations not SQL-first).

# Skill: XCELR8 Sales Process (Enquiry → Quotation → Booking → Transaction/OTF)

**When to activate:** Any task involving: Enquiry, Quotation, Booking, Transaction Sheet, OTF, VOTF,
`EnquiryCrudController`, `QuotationCrudController`, `BookingCrudController`, `standard_data`,
`final_data`, `QuoteAction`, booking status codes (1/8/6/2/3/4/5/7), receipts/Bookingamount, XFinance,
XlInsurance, XlRto, chassis allocation, Expected Balance, Ready-to-Invoice, or anything under
`routes/backpack/booking.php` / `app/Http/Controllers/Admin/Sales/**`.

**Keyword triggers:** enquiry, quotation, booking, transaction sheet, OTF, VOTF, standard_data,
final_data, QuoteAction, raised, booked, expected balance, ready to invoice, KYC, DMS, chassis
mismatch, sales consultant, DSA, financier, TCS.

---

## Context

The full, authoritative business-process spec is `docs/reference/Sales-Combined_FRS.md` (Functional
Requirements Specification, consolidated from the Enquiry/Quotation/Booking/Transaction module FRS
documents). **Read it before making behavioral changes to any Sales module code** — it documents the
cross-module contract (statuses, required fields, validations, financial formulas, audit behavior)
that the code is supposed to implement. Where the FRS marks something "Not Determinable From Provided
Information" or "Requires Business Confirmation," do not silently invent behavior — flag it and ask,
same as any other locked-spec conflict per `.ai/rules/index.md`.

## Module map (code ↔ FRS)

| FRS Module | Controller | Namespace |
|---|---|---|
| Enquiry | `EnquiryCrudController` | `App\Http\Controllers\Admin\Sales\Enquiry` |
| Quotation | `QuotationCrudController` | `App\Http\Controllers\Admin\Sales\Quotation` |
| Booking | `BookingCrudController` | `App\Http\Controllers\Admin\Sales\Booking` |
| Transaction Sheet / OTF | Booking-linked, no separate controller — `BookingCrudController`'s
  `otfProcess()`/`otfSave()`/`liveNotInvoiced()` methods (see `routes/backpack/booking.php`) | same |

Routes for all three live in `routes/backpack/booking.php` (Booking + Quotation + a couple of
Enquiry AJAX helpers) and `routes/backpack/core.php`/`routes/backpack/enquiry.php` where applicable —
check `php artisan route:list --path=sales` before assuming a route's location.

## Key documented rules to check code against (FRS §10, §17 — not exhaustive, see the FRS for the rest)

- Booking may originate from **either** an Enquiry (`enq_no`) or a Quotation (`quotation_id`) — never
  assume Quotation is mandatory for Booking creation.
- Duplicate Booking for the same Enquiry or Quotation must redirect to the existing Booking with a
  warning, not create a second one.
- Quotation → Booking conversion must set Quotation `status = booked` and record `QuoteAction =
  BOOKED`.
- Quotation requires Color, Permit and Financier to save (Color is optional at Enquiry stage but
  mandatory from Quotation onward).
- Booking requires Segment/Model/Variant/Color; Booking Date cannot be in the future; incomplete
  mandatory data → status `8` (Pending), not a hard failure.
- **Transaction Sheet / OTF can only be opened when `Booking.quotation_id` is present and the
  referenced Quotation row actually exists** — missing/deleted quotation must show a "Quotation
  Required" prompt, not a broken page.
- OTF data = `Quotation.standard_data` (proposal base) merged with `Booking.final_data` (finalized
  override); vehicle fields resolve `Booking → Enquiry → otfData` in that priority order.
- OTF save must merge into `Booking.final_data`, upsert related XFinance/XlInsurance/XlRto records,
  and create a Booking history entry titled `"OTF Form Saved"`.
- VOTF numbers, once generated, are never reused/reversed even if the OTF is later cancelled.
- Invoice/"Ready to Invoice" progression is gated on OTF having been processed AND Expected Balance
  = 0 (`Net Receivable - DO Amount - Receipt Amount + DO Settlement Difference`).
- Booking status codes are fixed: `1` Live, `8` Pending, `6` On Hold, `2` Invoiced, `3` Cancelled,
  `4` Refund Queue, `5` Refunded, `7` Refund Rejected — do not invent new codes or reuse these for
  different meanings.

## Known conflicts/gaps the FRS itself flags (don't "fix" these by guessing — ask first)

- CONFLICT-002: Quotation quotes TCS @ 1% (threshold-based); Transaction applies 5% TCS when
  PAN/Aadhaar aren't linked. Reconciliation between the two when they disagree is unresolved.
- CONFLICT-003/GAP-002: no formal "Quotation Accepted" status exists before Booking conversion, and
  the exact status an invalidated Quotation (after a chassis-mismatch vehicle replacement) should
  carry is not defined.
- GAP-003/GAP-004: no documented hard cap on multiple Quotations per Enquiry or multiple
  Transaction saves per Booking (OTF just re-merges into the same `final_data`).

## Related project rules

Also read `.ai/rules/module-structure.md` (route/permission structure — Booking/Quotation/Enquiry are
already migrated, `SLS_BKNG_*`/`SLS_QUOT_*`/`SLS_ENQR_*` permissions) and
`docs/refactor/known-bugs-report.md` (search for BUG-091, BUG-092, BUG-050, BUG-059 through BUG-062,
BUG-093 through BUG-097 — all Booking/Quotation-specific findings already on record; don't
re-discover them) before starting work in this area.

## Identifier format/validation registry (Aadhaar, PAN, mobile, GSTIN, chassis, etc.)

Business-identifier format validation and normalization is centralized — never write a new regex
for Aadhaar/PAN/TAN/mobile/GSTIN/chassis/employee-code/OTF/DMS/invoice numbers inline in a
FormRequest, controller, or importer. Use:

- `App\Rules\*` (`AadhaarNumber`, `PanNumber`, `TanNumber`, `IndianMobileNumber`, `Gstin`,
  `ChassisNumber`, `EmployeeCode`, `OtfNumber`, `DmsNumber`, `InvoiceNumber`,
  `DealerInvoiceNumber`) for validation — pass as `new AadhaarNumber` etc. in a `rules()` array.
- `App\Services\IdentifierService` (singleton, inject via constructor property promotion) for
  normalization — `cleanMobile()`, `normalizePan()`, `normalizeAadhaar()`, `normalizeTan()`,
  `normalizeGstin()`, `normalizeChassis()`.
- `App\Services\EnquiryReferenceService` (singleton) for the `XENQ-{id}` enquiry reference format
  — `toReference()`/`fromReference()`, never rebuild/parse the prefix manually.
- `Person::deriveCode()` (model static method) is the SSOT for `person_code` derivation
  (Aadhaar-first, PAN-second, `PERS-######` fallback) — never reimplement this in an importer.

Canonical formats and the government-standard-vs-project-convention rationale for each are recorded
in `docs/refactor/ai-changelogs-22-09-2026.md` ("Phase 1 of Sales-system refactor: Identifier &
Reference Registry"). See BUG-097 (open) for a known VOTF-generation race-condition gap this
registry didn't fix.

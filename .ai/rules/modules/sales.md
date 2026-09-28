---
description: Enquiry, quotation, booking and accounts flows. Load for Sales/CRM/Booking/Accounts work.
paths:
  - app/Http/Controllers/Admin/Sales/**
  - app/Http/Controllers/Admin/Accounts/**
  - app/Services/Sales/**
  - app/Models/CRM/**
  - app/Models/Module/**
  - app/Jobs/ImportEnquiriesJob.php
  - resources/views/admin/sales/**
  - resources/views/admin/accounts/**
---

# Sales (Enquiry → Quotation → Booking) & Accounts

## Booking (Track A)
- `BookingCrudController` is a ~10.8k-line god controller being carved into the tested services in
  `App\Services\Sales\Booking\*` — put new/changed logic in the matching service, never back into the controller.
- `xlr8_booking_master.status` is a varchar '1'–'8' (1 Live, 2 Invoiced, 3 Cancelled, 4 Refund Queued,
  5 Refunded, 6 On Hold, 7 Refund Rejected, 8 Pending) — use the service constants, not new magic numbers.
- Bookings carry `branch_code`, `location_code`, `segment_code`, `sub_segment_code`, `model_code`, `variant_code` (DEC-071),
  filled on save by `ScopeCodeFiller` (enquiry, quotation, masters; the OTF branch when empty). The VOTF number uses the
  branch the user picks on the OTF form (`generateVotfNumber($booking, $branchCode)`).
- Sales / booking models are **data-scoped automatically** (`HasDataScope`, DEC-071): uniqueness, numbering and duplicate
  checks must use `withoutDataScope()` / `DataScope::off()`; per-user caches key on `DataScope::current()->hash()`.
- `booking.consultant` holds a person_code. History via `$booking->recordEvent(ACTION, $title, $meta, $body)` (Chat;
  actions CREATED / STATUS_CHANGED / UPDATED) — `addHistory()` is legacy. No full Aadhaar / account numbers in `$meta`.
- Duplicate models for booking satellite tables were removed (DEC-030): use `Module\Booking\*` ones.

## Quotation (FRS v1.0, July 2026)
- Form locked until a valid enquiry resolves; reject closed/converted/cancelled enquiries.
- Variant → colour → `QuotationPricingService::forVehicle(oemCode)` (published snapshots via `getPricing`, DEC-082); no mock
  prices. The enquiry's codes are prefilled and locked when present (enquiries mostly lack them). UI is driven only by pricing keys.
- Maxicare/PPF/Ceramic are accessories, not grid rows. Discount types I, C, C1, C2, B.
- Gate: any ordinary C > 0 ⇒ CreditNoteDiscount ≥ OEM scheme (C1/C2 excluded both sides). Server re-validates gate + TCS on save
  (`validateSubmission`); `standard_data.pricing` keeps the published pricing. A held price list blocks quotation save and booking.
- TCS = 1% of FinvoiceAmount (Subtotal − InvoicedDiscount), threshold ₹10,00,000 or financier invoice.
- Quotation does not create bookings in v1. Approvals are a **parallel counter-offer** engine (highest level wins,
  approvers never reject) — built in Track B per FRS §7–8; don't build approve/reject chains here.

## Enquiry
- `xlr8_crm_enquiries` (142 columns, 60k rows) is being normalised; `stage` holds Title-case strings.
- Reference format `XENQ-{id}` via `EnquiryReferenceService`; resolve any form with `Enquiry::resolveByAnyReference()`.

## Accounts
- Receipts/JV write `xlr8_booking_amount`; numbering uses `lockForUpdate()` sequences. No delete operation.
- The legacy `xlrk` database is reference-only, never SSOT for pricing or quotation math.

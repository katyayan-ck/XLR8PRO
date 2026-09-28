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
- Bookings have **no branch column**; display copies branch from the linked enquiry; VOTF uses enquiry
  `dealer_branch` → FSC (consultant person_code → employee primary branch) (BUG-161, DEC-029).
- `booking.consultant` holds a person_code. History via `$booking->addHistory(...)` (EntityHistoryService).
- Duplicate models for booking satellite tables were removed (DEC-030): use `Module\Booking\*` ones.

## Quotation (FRS v1.0, July 2026)
- Form locked until a valid enquiry resolves; reject closed/converted/cancelled enquiries.
- Variant → colour → `getPricing(oemCode)`; UI is driven only by pricing JSON keys (missing key = hidden).
- Maxicare/PPF/Ceramic are accessories, not grid rows. Discount types I, C, C1, C2, B.
- Gate: any ordinary C > 0 ⇒ CreditNoteDiscount ≥ OEM scheme (C1/C2 excluded both sides). Server re-validates on save; store full snapshot.
- TCS = 1% of FinvoiceAmount (Subtotal − InvoicedDiscount), threshold ₹10,00,000 or financier invoice.
- Quotation does not create bookings in v1. Approvals are a **parallel counter-offer** engine (highest level wins,
  approvers never reject) — built in Track B per FRS §7–8; don't build approve/reject chains here.

## Enquiry
- `xlr8_crm_enquiries` (142 columns, 60k rows) is being normalised; `stage` holds Title-case strings.
- Reference format `XENQ-{id}` via `EnquiryReferenceService`; resolve any form with `Enquiry::resolveByAnyReference()`.

## Accounts
- Receipts/JV write `xlr8_booking_amount`; numbering uses `lockForUpdate()` sequences. No delete operation.
- The legacy `xlrk` database is reference-only, never SSOT for pricing or quotation math.

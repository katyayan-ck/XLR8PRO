# CRM — leads, enquiries, quotations, test drives, campaigns

The front of the sales funnel: **lead → enquiry → quotation (with revisions / approvals) → booking**
([sales-booking.md](sales-booking.md)). Owned by the booking / sales team. There are no CRM service classes yet — the
logic lives in the controllers (`Admin\Sales\{Enquiry,Quotation,Lead,Campaign}*`); this page documents the models and
the shared helpers they use.

| Need | Use |
|---|---|
| find an enquiry from any stored reference (id, `enquiry_no`, quick no, `XENQ-12`) | `Enquiry::resolveByAnyReference($ref)` / `EnquiryReferenceService::fromReference($ref)` |
| write a cross-reference into a booking / receipt / JV | `EnquiryReferenceService::toReference($enquiryId)` → `XENQ-12` |
| enquiry list highlight filters | `OrgService::applyHighlightFilter($query, $filter)` ([org.md](org.md)) |
| customer / transaction lookup across enquiry, booking, VOTF | `OrgService::getCustomerByTransactionIds($enq, $booking, $votf)` |
| on-road price for a quote | `QuotationPricingService::forVehicle($oemCode)` → published prices in the screen's shape ([pricing.md](pricing.md)) |
| extra discount approval | the approval engine (tech-guides/platform/07-approvals.md) — not wired into quotations yet |

---

## Models (`App\Models\CRM`)

### Lead (`xlr8_crm_leads`)
Fields: `lead_no`, `capture_date`, `source_code`, `referral_details`, `first_name`, `last_name`, `mobile`, `email`,
`occupation`, segment / model / variant / colour codes, `expected_delivery_date`, `notes`, `status`, `priority`,
`assigned_to`, `verified_by` …
Status constants: `new`, `in_followup`, `quotation_sent`, `booking_done`, `otf_generated`, `lost`, `cancelled`.

| Member | Returns |
|---|---|
| `source()` → `LeadSource`, `assignedTo()` / `verifiedBy()` → `User`, `enquiry()` (hasOne on `lead_id`) | relations |
| `model()`, `segment()`, `variant()`, `color()` | vehicle by code (`color()` uses the retired colour table) |
| scopes `open()`, `forFsc($userId)`, `byStatus($status)` | filters |
| `full_name`, `model_name`, `variant_name`, `color_name` | accessors |
| `static getOpenCountByConsultant($userId)` (cached) / `clearConsultantCache($userId)` | counters |

### LeadSource (`xlr8_crm_lead_sources`)
`code`, `name`, `description`, `is_active`, `sort_order`; `leads()`; `static getActiveCached()` / `clearCache()`.

### Enquiry (`xlr8_crm_enquiries`)
Guarded model. Status constants: `new`, `in_followup`, `quotation_sent`, `quotation_approved`, `booking_done`,
`otf_generated`, `lost`, `cancelled`. Long-form fields: `LONG_FORM_PAIRED_FIELDS` (segment / model / variant code ↔
name) and `LONG_FORM_SINGLE_FIELDS` (name, mobile, email, gender, enquiry_type, source_code, likely_purchase_date,
fuel_type, transmission, drivetrain, seating, color_code, tehsil, district, …).
**Data scoping (DEC-071):** `HasDataScope` — every query is filtered by the user's scope on `dealer_branch`,
`dealer_location`, `segment_code`, `model_code`, `variant_code`; a `saving` hook (`ScopeCodeFiller::fillEnquiry`) fills
empty branch / location from the acting employee's primary branch / location and vehicle parents from the masters.
`Quotation` and `Lead` / `Campaign` are scoped too (quotation via its enquiry). The duplicate-enquiry check uses
`withoutDataScope()`; menu / highlight counts are cached per scope. Existing rows: `php artisan data-scope:backfill`.

| Member | Returns |
|---|---|
| `static resolveByAnyReference($ref)` | `?Enquiry` by id **or** `enquiry_no` **or** `quick_enquiry_no` |
| `lead()`, `person()`, `salesConsultant()` (`sc_name` → user), `campaign()` (`planned_campaign` → name) | relations |
| `model()`, `segment()`, `variant()`, `color()`, `vehicleModel()` | vehicle relations |
| `quotations()` | `hasMany(Quotation, 'enquiry_no', 'id')` — quotations store the enquiry id (BUG-192 fixed) |
| scopes `formComplete()` / `formIncomplete()` | long form filled / not |
| scopes `mainListing()`, `xceler8()` (created in the CRM), `hyperlocal()`, `currentOrigin($origin)`, `quick()`, `long()`, `reference()`, `virtual()`, `whatsapp()` | list sources |
| scopes `open()`, `forConsultant($userId)`, `assigned()`, `unassigned()`, `assignedQuick()`, `unassignedQuick()`, `assignedLong()`, `unassignedLong()` | work lists |
| `full_name`; `static getOpenCountByConsultant($userId)` / `clearConsultantCache($userId)` | helpers |

`Enquiry`, `Lead` and `Quotation` use `HasCommunications` (DEC-068): `$enquiry->recordEvent(...)`, `->addRemark(...)`,
`->history()`, `<x-chat.thread :model="$enquiry" />`. `HasColumnTransformations` stays off on Enquiry / Lead (it would
change stored values).

**Schema notes (DEC-088, 30-09):** `cre_lost_reason` / `cre_lost_sub_reason` hold the CRE's lost reasons (lost-enquiry
screens); the vehicle field is `vh_code` (was `vh_id`); `mobile` is indexed (`idx_mobile`) for the duplicate check.

### Quotation (`xlr8_crm_quotations`) and QuoteAction (`xlr8_crm_quote_actions`)
Quotation fields: `quotation_no`, `enquiry_no` (holds the **enquiry id**), `booking_id`, `person_code`, vehicle codes,
`sc_code`, `assigned_to`, `revision`, `standard_data` (the pricing JSON quoted), `final_data` (after edits),
`onroad_price`, `invoice_price`, `status`, …
Status constants: `raised`, `pending_approval`, `approved`, `rejected`, `revised`, `closed`.

| Member | Returns |
|---|---|
| `enquiry()`, `person()`, `salesConsultant()`, `assignedTo()`, `vehicleModel()`, `variant()`, `color()` | relations |
| `actions()` | `QuoteAction` rows (`quotation_no`): `action_by`, `action`, `revision`, `requested`, `onroad`, `status`, `remarks`; each has `quotation()` and `actionBy()` (→ `User`) |
| `status_label`, `latest_action` | accessors |
| media collections | quotation PDFs |

### TestDrive (`xlr8_crm_testdrive`) and Campaign (`xlr8_crm_campaigns`)
- `TestDrive`: guarded; `enquiry()` by `enquiry_no`.
- `Campaign`: `name`, `segment_code`, `model_code`, `activity_code`, `start_date`, `end_date`, `branch_code`,
  `location_code`; `segment()`, `model()`, `createdBy()` / `updatedBy()` / `deletedBy()`; `segment_name`, `model_name`;
  `static clearCache()`.

## EnquiryReferenceService (`App\Services\EnquiryReferenceService`)
| Method | Returns |
|---|---|
| `toReference(int $enquiryId)` | `'XENQ-123'` |
| `fromReference(string\|int\|null $ref)` | `123` for `XENQ-123`, `xenq-123` or `'123'`; `null` otherwise |
Bookings, receipts and journal vouchers store this string (not a foreign key); resolve it with `fromReference()` or
`Enquiry::resolveByAnyReference()`.

## Use cases
**Open enquiries of the signed-in consultant**
```php
$rows = Enquiry::query()->open()->forConsultant(backpack_user()->id)->latest('id')->paginate(25);
$badge = Enquiry::getOpenCountByConsultant(backpack_user()->id);    // cached; call clearConsultantCache() after assignment changes
```

**From a receipt back to the enquiry**
```php
$enquiry = Enquiry::resolveByAnyReference(app(EnquiryReferenceService::class)->fromReference($receipt->enq_no) ?? $receipt->enq_no);
```

**Quote history** → `$quotation->actions()->latest()->get()` (who requested / approved which on-road, per revision).

## Gotchas
- `Quotation.enquiry_no` is the enquiry **id**, not its `enquiry_no` (`Enquiry::quotations()` joins on it).
- Status strings are constants on the models — compare with `Enquiry::STATUS_LOST`, never string literals.
- Enquiry, TestDrive and Booking are guarded (no `$fillable`); controllers set attributes explicitly — don't mass-assign
  request input.

## Testing
Enquiry / quotation rows may be absent in `xlrm_testing` — create them inside the transaction or `markTestSkipped()` as
the platform tests do.

## Quotation pricing (DEC-082, `App\Services\Sales\Quotation\QuotationPricingService`)
The quotation screen reads **published prices only** (the hard-coded mock enquiry / BE6 prices are gone).

| Method | Returns |
|---|---|
| `forVehicle(string $oemCode, ?string $date = null): Result` | ok `{oem_code, screen, contracts{permit: contract v2}, hold}` · fail `NOT_FOUND` · fail `ON_HOLD` (same data) |
| `screen(array $contracts): array` | the screen's pricing shape: `permit[]`; `receivables` (ex-showroom, insurance per permit with GST-inclusive heads — mandatory = default add-ons, `RTO {TRC, TAX[]}`, RSA / Shield choices, charges, `tcs {limit, rate}`); `deductibles` (types in `TYPES`); `live = true` |
| `validateSubmission(array $input, array $tcs): Result` | ok `{invoice, tcs, inv_side, cn_side}` · fail `GATE` (Group A INV / INV_OE scheme > total CN) · fail `TCS` (submitted TCS off by more than ₹1 from rate % × invoice when invoice ≥ limit) |
| `vehicleOptions(string $level, ?string $parent)` | picker rows `{code, name}` for `segment`, `model`, `variant` (one per OEM variant, `VehicleService::variantGroupOptions()`), `colour` (full OEM codes with a published NV price) |

- **Screen:** create mode shows Segment → Model → Variant → Colour pickers. Codes the enquiry already has are prefilled
  and locked. The colour loads `sales.quotation.pricing` (JSON; 404 / 423) into the screen's fill routine
  (`applyPricing`). Saving stays disabled until prices load and the list is open.
- **Save:** `store()` requires an OEM code; `store()` / `update()` re-fetch the prices and refuse a held list, a broken gate
  or a wrong TCS. They store `standard_data.pricing` = `{oem_code, wef_date, checked_at, screen, contract}`; edit mode
  reloads `screen` as `PRICING.saved`. Legacy quotations without an OEM code are edited as before.
- **Booking:** create warns, and store refuses, when the linked quotation's price list is on hold
  (`PricingQueryService::holdMessage()`).
- **Discount defaults (flagged, DEC-082):**
  - consumer scheme → Cash Scheme OEM (INV_OE);
  - cash → dealer discount (CN1);
  - accessory (INV_OE) and Shield (CN1) schemes;
  - RSA discount → other cash discount (CN1);
  - corporate INV, exchange CN2 — offered, never auto-applied.

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
| on-road price for a quote | `PricingEngineService::getPricingPayload()` ([pricing.md](pricing.md)) |
| extra discount approval | the approval engine (docs/utilities/07-approvals.md) — not wired into quotations yet |

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

| Member | Returns |
|---|---|
| `static resolveByAnyReference($ref)` | `?Enquiry` by id **or** `enquiry_no` **or** `quick_enquiry_no` |
| `lead()`, `person()`, `salesConsultant()` (`sc_name` → user), `campaign()` (`planned_campaign` → name) | relations |
| `model()`, `segment()`, `variant()`, `color()`, `vehicleModel()` | vehicle relations |
| `quotations()` | **broken** — joins on `enquiry_no` instead of `id` (BUG-192); query `Quotation::where('enquiry_no', $enquiry->id)` |
| scopes `formComplete()` / `formIncomplete()` | long form filled / not |
| scopes `mainListing()`, `xceler8()` (created in the CRM), `hyperlocal()`, `currentOrigin($origin)`, `quick()`, `long()`, `reference()`, `virtual()`, `whatsapp()` | list sources |
| scopes `open()`, `forConsultant($userId)`, `assigned()`, `unassigned()`, `assignedQuick()`, `unassignedQuick()`, `assignedLong()`, `unassignedLong()` | work lists |
| `full_name`; `static getOpenCountByConsultant($userId)` / `clearConsultantCache($userId)` | helpers |

`Enquiry`, `Lead` and `Quotation` use `HasCommunications` (DEC-068): `$enquiry->recordEvent(...)`, `->addRemark(...)`,
`->history()`, `<x-chat.thread :model="$enquiry" />`. `HasColumnTransformations` stays off on Enquiry / Lead (it would
change stored values).

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
- `Quotation.enquiry_no` is the enquiry **id**, not its `enquiry_no` (BUG-192).
- Status strings are constants on the models — compare with `Enquiry::STATUS_LOST`, never string literals.
- Enquiry, TestDrive and Booking are guarded (no `$fillable`); controllers set attributes explicitly — don't mass-assign
  request input.

## Testing
Enquiry / quotation rows may be absent in `xlrm_testing` — create them inside the transaction or `markTestSkipped()` as
the platform tests do.

# Accounts — receipts and journal vouchers

Money received against enquiries / bookings (receipts) and internal transfers between heads (journal vouchers). Both are
rows of **one table**, `xlr8_booking_amount`, model `App\Models\Module\Booking\Bookingamount`, told apart by `type`.
There is no accounts service yet; the logic sits in `Admin\Accounts\Receipt\ReceiptCrudController` and
`Admin\Accounts\JournalVoucher\JournalVoucherCrudController`.

| `type` | Meaning | Number format | Permissions |
|---|---|---|---|
| `1` | Receipt | `{prefix}{location}{F + FY end yy}{seq}` from `generateReceiptNumber($accountOf, $locationCode)` (prefix by account head), under `lockForUpdate()` | `ACC_RCPT_VIEW`, `ACC_RCPT_CREATE`, `ACC_RCPT_EDIT` |
| `2` | Journal voucher | `JV{location}{F + FY end yy}{seq}`, e.g. `JVJPRF27` + sequence, generated under `lockForUpdate()` | `ACC_JRVCH_VIEW`, `ACC_JRVCH_CREATE`, `ACC_JRVCH_EDIT` |

## Bookingamount (`xlr8_booking_amount`)
Columns: `bid` (booking id), `enq_id` (enquiry reference), `date`, `type`, `type_number` (receipt / voucher no.),
`jv_cat`, `account_of`, `mode`, `amount`, `trans_date`, `trans_no`, `instrument_no`, `bank`, `hypo`, `chassis_no`,
`vh_rgn_no`, `otf_no`, `inv_no`, `location`, customer fields (`name`, `care_of_type`, `care_of`, `address`, `mobile`,
`alternate_mobile`), `used_model`, `used_rgn_no`, `from_dept`, `to_dept`, `exist_receipt_no`, `party_name`, `remarks`,
`status`. Guarded model; the payment proof is the Docs slot `amount-proof` (`HasDocuments`, DEC-069).

| Member | Returns |
|---|---|
| `booking()` | `BelongsTo Booking` (`bid`) |
| `Booking::bookingAmounts()` / `Booking::totalReceivedAmount()` | receipts of a booking / their sum (the SSOT for "amount received") |
| `OrgService::checkReceiptX($no)` | `1` when a receipt / voucher number already exists |

Enquiry references in `enq_id` use the `XENQ-{id}` format — `EnquiryReferenceService::toReference()` /
`fromReference()` ([crm-enquiry-quotation.md](crm-enquiry-quotation.md)).

## Use cases
**Amount received on a booking** → `$booking->totalReceivedAmount()`.
**Receipts of one enquiry**
```php
Bookingamount::query()->where('type', 1)->where('enq_id', app(EnquiryReferenceService::class)->toReference($enquiry->id))->orderBy('date')->get();
```
**Display** dates with `@sitedate($row->date)`, amounts with `number_format()`; receipt print views are paper output.

## Gotchas
- Always filter by `type` — receipts and vouchers share the table.
- Numbers are generated per location and financial year; never build them yourself — reuse the controller's generator
  (a future `AccountsService` should own it). Both generators read the last number under `lockForUpdate()`.
- The booking-side receipt created by `BookingCoreService::store()` is also a `type = 1` row.

## Testing
Receipt / voucher screens are covered by the admin smoke group; for logic tests create rows through the controllers'
store routes as superadmin (numbers depend on existing rows in `xlrm_testing`).

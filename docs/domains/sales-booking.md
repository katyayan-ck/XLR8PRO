# Sales — booking and its sub-domains (KYC, DMS, OTF, finance, insurance, RTO, exchange, delivery, refunds)

A **booking** (`xlr8_booking_master`) is created from a quotation / enquiry and then moves through independent
"pending" work-lists, each owned by one service. Controllers (`Admin\Sales\Booking\BookingCrudController`) validate
and call exactly one of these services. Owned by the booking team — coordinate before changing behaviour.

> Updated for the Sales team's 27-09 changes (branch picked on the OTF form, `body_type` on the booking) merged on
> 28-09-2026.

| Stage / list | Service | Main calls |
|---|---|---|
| create / edit booking | `BookingCoreService` | `store($input)`, `update($booking, $input)` |
| KYC (PAN / Aadhaar / GST) | `BookingKycService` | `resolveEditData($b)`, `apply($b, $validated, $gstNotRequired)` |
| DMS / order | `BookingDmsService` | `resolveEditData($b, $fromPending)`, `apply($b, $validated, $dmsSoApplies)` |
| OTF / VOTF | `BookingOtfService` | `resolveOtfFormData($b)`, `apply($b, $formData, $chassisImage)`, `generateVotfNumber($b, $branchCode)` |
| finance, retail, payout | `BookingFinanceService` | `resolveFinEditData`, `resolveRetailEditData`, `resolvePayoutEditData`, `resolveFinanceViewData`, `apply`, `applyPayout` |
| insurance | `BookingInsuranceService` | `resolveEditData($b)`, `apply($bookingId, $validated, $policyCopy)` |
| RTO | `BookingRtoService` | `permitMap()`, `resolveEditData($b)`, `apply(...)`, `hasExistingMedia($id, $collection)` |
| exchange / scrappage | `BookingExchangeService` | `resolveEditData($b)`, `apply($b, $validated)` |
| delivery | `BookingDeliveryService` | `resolveEditData($b)`, `apply($bookingId, $remarks, $chassisNoVerified, $photos)` |
| refunds | `BookingRefundService` | `resolveRefundDisplayData`, `apply`, `applyRefundDetailsEdit`, `applyRefundUpdate`, `applyRefundedUpdate` |

All services live in `App\Services\Sales\Booking`, are instance services (inject or `app()`), and record a booking
history entry for every completed step (through the Chat timeline — `HasCommunications` on `Booking`).

---

## Booking model (`App\Models\Module\Booking\Booking`, `xlr8_booking_master`)
Guarded model (no `$fillable` — the services set columns explicitly). Traits: `HasCommunications`, media, soft deletes.

`status` is a varchar `'1'`–`'8'`; `consultant` holds a person code. **Status codes** (`status`): `1` live · `8` live, pending data · `2` invoiced · `3` cancelled · `4` refund queued ·
`5` refunded · `6` on hold · `7` refund rejected. **Order** (`order`): `1` requested · `2` verified · `3` ordered.
**Type** (`b_type`): `Active` / `Dummy`. Sub-records use `status` `1` = saved, incomplete / `2` = complete
(insurance, RTO) and delivery `1` = delivered.

| Member | Returns |
|---|---|
| `segment()` | by `segment_code` |
| `branch()`, `location()` | **always null** — the table has no `branch_code` / `location_code`; the branch comes from the linked enquiry (`dealer_branch`) or the consultant's employee record (BUG-161, DEC-029). `resolve*EditData()` fills `branch_code` etc. onto the model as display-only attributes |
| `bookingAmounts()` | `Bookingamount` receipts (`bid`) |
| `finances()`, `exchanges()` | **broken** — join on `booking_id`, the tables use `bid` (BUG-193); query `XFinance::where('bid', $id)` / `XExchange::where('bid', $id)` |
| `vehicle()` | legacy `XVehicleMaster` link |
| `totalReceivedAmount(): float` | sum of live receipts — **use this** instead of summing yourself |
| scopes `liveAll()`, `live()`, `pendingDataAll()`, `pendingData()`, `activeBooking()`, `dummyBooking()`, `onHold()`, `invoiced()`, `pendingInvoice()`, `cancelled()`, `refundQueued()`, `refunded()`, `refundRejected()`, `requestOrder()`, `verifiedOrder()`, `ordered()`, `hotEnquiries()`, `olderThan($days)`, `pendingKYC()`, `pendingDMS()`, `pendingRegNo()` | work-list filters (see codes above) |
| scopes `pendingPayment()`, `pendingInsurance()`, `pendingRTO()`, `pendingDeliveries()`, `pendingDO()` (declared as `scopependingDO`) and statics `getDynamicBookingCounts()`, `getFinanceCounts()`, `getFinanceMTDPercent()`, `getFinanceYTDPercent()`, `getExchangeScrappageCounts()`, `getExchangeScrappageMTDPercent()`, `getExchangeScrappageYTDPercent()`, `getTSTMaxAge()`, `getBookingsOlderThan()` | **broken legacy** (old `xcelr8_*` tables) — BUG-191; the controller builds these lists itself |

**Related models:** `Bookingamount` (`xlr8_booking_amount`, receipts, media), `XExchange` (`xlr8_booking_exchange`,
`seedForBooking($id, $purchaseType)`; `booking()`, `getVerifiedCounts()`, `getPendingCounts()` broken — BUG-193), `Module\Finance\XFinance` (`xlr8_booking_finance`; `booking()`, `getVerifiedCounts()`, `getPendingCounts()` broken — BUG-193), `Module\Insurance\XlInsurance`
(`xlr8_booking_insurance`), `XlRto` (`xlr8_booking_rto`) + `XlRtoRules` (which RTO fields are required for a
combination), `XlDelivery` (`xlr8_booking_delivered`, photo collections), `Xl_Refunds` (`xlr8_booking_refund`),
`Xl_DSA_Master` (DSAs / promoters), `XlFinancier`, `XlInsurer` (duplicated under `Module\Booking` and
`Module\Finance` / `Module\Insurance` — same tables), `Stock` (`xlr8_booking_stock_master`), `Xessories`
(booking accessories; `itemName()` points at a missing `App\Models\XessoriesItems` class — legacy).

---

## Services in detail

### BookingCoreService
- `store(array $input): Booking` — creates the booking from the raw request; converts the linked quotation (status,
  `QuoteAction` history, seeded insurance / RTO rows from the quote's `standard_data`), syncs or creates the enquiry,
  records history, creates the first `Bookingamount` receipt (no file — proofs come from the receipt screens), seeds `XExchange` /
  `XFinance` when the purchase type / finance mode needs them.
- `update(Booking $b, array $input): Booking` — diffs every field into a readable change log, updates booking and
  enquiry fields, seeds `XExchange` on the first switch to "Exchange Buy", upserts finance **without nulling fields
  that were disabled on the form**.

### BookingKycService
- `resolveEditData(Booking $b): array` — names for branch / location / segment / model / variant / colour and customer,
  falling back to the enquiry. **Mutates `$b`** (fills the fallbacks onto it) — the form pre-fills from the model.
- `apply(Booking $b, array $validated, bool $gstNotRequired): Booking` — `$validated = ['pan_no', 'adhar_no', 'gst_no']`,
  normalised by `IdentifierService`; records "KYC Completed".

### BookingDmsService
- `resolveEditData(Booking $b, bool $fromPending): array` — DMS fields with enquiry fallbacks (mutates `$b`).
- `apply(Booking $b, array $validated, bool $dmsSoApplies): Booking` — `$validated = ['dms_no', 'dms_otf', 'otf_date',
  'dms_so']`; recomputes the pending-items list, moves `order` 2 → 3 when `dms_so` is given, records "Pending Order
  Processed". `dms_so` is saved only when `$dmsSoApplies` (order = 2).

### BookingOtfService
- `resolveOtfFormData(Booking $b): ?array` — everything the OTF form shows; `null` when the linked quotation is missing.
- `apply(Booking $b, array $formData, ?UploadedFile $chassisImage): Booking` — merges submitted data **over** saved
  `final_data` **over** quotation data; keeps important price fields when a disabled input didn't submit them; saves
  `final_data`, KYC / DMS / exchange / chassis / invoice columns, enquiry address, and upserts RTO / finance / insurance.
- `generateVotfNumber(Booking $b, string $branchCode): string` — `"{FY}/{BRANCH}{branchSeq:04d}/{globalSeq:04d}"` for the
  branch the user picked on the OTF form (since 27-09; before, it was derived from the enquiry / consultant —
  DEC-027/029). A blank branch throws `InvalidArgumentException` (the controller answers 422 "Please select a branch
  first"). Scan-then-increment without a lock (BUG-097 — concurrent saves can collide).
- Branch list: after the stage merge this uses `OrgService` (their new code called the deleted `CommonHelper`).

### BookingFinanceService
- `resolveFinEditData($b)` / `resolveRetailEditData($b)` → `['finance' => ?XFinance, 'data' => [...], 'dsaname' => …]`;
  `resolvePayoutEditData($b)` adds `bookingHistory`; `resolveFinanceViewData($b)` → `['finance', 'data']`.
- `apply(Booking $b, array $validated, bool $hasInstrumentProofFile, bool $deleteInstrumentProof): XFinance` — create /
  update, clears mode-dependent fields, "new + retail" auto-verification, instrument-proof upload / removal, history for
  finance-completed and retail.
- `applyPayout(Booking $b, XFinance $f, array $validated): XFinance` — the finance row must exist; "Payout Completed".
- Labels: `instrumentTypeLabel($v)`, `caseLostReasonLabel($v)`, `verificationStatusLabel($v)`.

### BookingInsuranceService
- `resolveEditData($b)` → `['insurance' => ?XlInsurance, 'data' => [...dropdowns...], 'dsaname']` (mutates `$b`).
- `apply(int $bookingId, array $validated, ?UploadedFile $policyCopy): XlInsurance` — `$validated = ['insurance_category',
  'insurance_company', 'policy_no', 'hidden_policy_date', 'policy_type']`; `status = 2` only when every field **and** a
  policy copy are present in this submission (else `1`); "Insurance Process Completed".

### BookingRtoService
- `permitMap(): array` (permit labels), `resolveEditData($b)` → `['rto', 'data', 'dsaname']`.
- `apply(int $bookingId, array $validated, ?UploadedFile $trcCopy, ?UploadedFile $taxReceiptCopy): XlRto` — looks up
  `XlRtoRules` for the sale / permit / body / registration / reg-no-type combination to know which optional fields are
  required (an already uploaded file satisfies a file requirement); `status = 2` when complete.
- `hasExistingMedia(int $bookingId, string $collection): bool`.

### BookingExchangeService
- `resolveEditData($b)` → `['exchange' => ?XExchange, 'data', 'dsaname', 'uid', 'bookingHistory']` — buyer type,
  prices, referee and address live on the **enquiry**.
- `apply(Booking $b, array $validated): ['exchange' => XExchange, 'changes' => list<string>]`.

### BookingDeliveryService
- `resolveEditData($b)` → `['insurance' => ?XlInsurance, 'rto' => ?XlRto, 'data']`.
- `apply(int $bookingId, string $remarks, bool $chassisNoVerified, array $photos): XlDelivery` — `$photos` keyed by
  `PHOTO_COLLECTIONS` (`delivery_ceremony_with_customer`, `bonnet`, `windshield_glass`, `vehicle_driver_side`, …); each
  goes to its own Docs slot on the delivery (`replaceDocument`, error field `photos.{collection}`); "Delivery Process Completed".

### BookingRefundService
- `resolveRefundDisplayData($b)` → `['refund' => ?Xl_Refunds, 'amount', 'deduction', 'acc_proof', 'aadhar', 'pan',
  'pay_proof', 'refundDetails']` (latest refund).
- `apply($b, $validated, $files)` — creates the refund, attaches `acc_proof` / `aadhar` / `pan` (one failed upload is
  logged, not fatal), booking → status `4`; "Refund Requested Again" when the booking was `7`.
- `applyRefundDetailsEdit($b, $refund, $validated)` — bank details / deduction / remaining amount; status unchanged.
- `applyRefundUpdate($b, $refund, $validated, $payProof)` — mandatory pay proof, booking → `5`.
- `applyRefundedUpdate($b, $refund, $validated, $payProof)` — edit an already refunded record; history diff only.

---

## Use cases
**Show the pending-KYC list**
```php
$rows = Booking::query()->pendingKYC()->latest('id')->paginate(50);
// bookings carry no branch column: to limit to the user's branches, join the enquiry (enq_no → XENQ-{id}) and filter
// on its dealer_branch (the controller selects `enq.dealer_branch as branch_code` for the same reason).
```

**Complete KYC from a controller**
```php
if (! backpack_user()->can('SLS_BKNG_KYC')) { abort(403); }
$validated = $request->validate([...]);                      // names from resources/lang/en/sales.php
$booking = app(BookingKycService::class)->apply($booking, $validated, $request->boolean('gst_not_required'));
```

**Balance due on a booking** → `$booking->final_data['on_road'] - $booking->totalReceivedAmount()` (on-road from the
OTF / quote JSON — see [pricing.md](pricing.md)).

## Gotchas
- `resolve*EditData()` methods **mutate the booking** on purpose; don't pass a model you will save afterwards unless you
  mean to persist those fallbacks.
- Receipts belong to Accounts (`Bookingamount`); never sum amounts in a view.
- Booking history is written with `$booking->recordEvent(ACTION, $title, $meta, $body)` (DEC-068; before, `addHistory('commented', …)`).
  Actions: `CREATED`, `STATUS_CHANGED` (hold / resume / restore / refund moves), `UPDATED` (everything else). Never put full
  Aadhaar / account numbers into `$meta` — the timeline is widely visible (BUG-195).
- Proofs (receipt `amount-proof`, finance `instrument_proof`, insurance `policy_copy`, RTO `trc_copy` / `tax_receipt_copy`,
  refund `acc-proof` / `aadhar` / `pan` / `pay-proof`, delivery photos, booking `chassis_image`) are Docs one-file slots on
  the satellite record (DEC-069): write with `$record->replaceDocument($collection, $file, [], $field)`, read with
  `documentFor()` / `documentUrl()` / `hasDocumentIn()`, clear with `removeDocuments()`. Links go through the
  access-checked download route — never `getFirstMediaUrl()`. Migration `2026_09_28_150000_move_booking_proofs_to_docs`
  moved the old media rows (reversible; BUG-196 misspelled insurer type folded in).
- Booking tests exist per sub-domain (`tests/Unit/Services/Sales/Booking*ServiceTest.php`, 9 files — no RTO test yet); keep them green when touching a service.

## Testing
`php artisan test --filter=Booking` — each service has a feature test using real bookings from `xlrm_testing`.

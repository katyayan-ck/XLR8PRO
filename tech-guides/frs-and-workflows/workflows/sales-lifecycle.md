# Workflow — the sales lifecycle (lead → enquiry → quotation → booking → delivery)

**Detail:** `modules/crm-enquiry-quotation.md`, `modules/sales-booking.md`. **Spec:** `frs/sales-combined-frs.md`.
**Rules (auto):** `.ai/rules/modules/sales.md`. **Skill:** `xcelr8-sales-process`. The booking screens are owned by the
booking team — coordinate before changing behaviour.

```
Lead ──► Enquiry ──► Quotation ──(approve / reject / revise)──► Booking ──► work-lists ──► Delivery
 (CRM)   quick/long   priced from        QuoteAction rows          created from   KYC · DMS/order · OTF/VOTF ·
                      published prices   (legacy approval)         the quotation  finance · insurance · RTO ·
                      (getPricing)                                                exchange · delivery · refunds
```

## Statuses
- **Enquiry** (`xlr8_crm_enquiries`): `new` → `in_followup` → `quotation_sent` → `quotation_approved` → `booking_done`
  → `otf_generated`; or `lost` / `cancelled`. Quick and long forms; duplicate check ignores data scope.
- **Quotation** (`xlr8_crm_quotations`, actions in `xlr8_crm_quote_actions`): `raised` → `pending_approval` →
  `approved` / `rejected` → `revised` (new revision) → `closed`. `standard_data` = the pricing JSON quoted,
  `final_data` = after edits.
- **Booking** (`xlr8_booking_master`): created from the quotation; then independent "pending" work-lists, each owned by
  one service in `App\Services\Sales\Booking` (Core, Kyc, Dms, Otf, Finance, Insurance, Rto, Exchange, Delivery, Refund).
  Every completed step writes a booking history entry (Chat timeline).

## Quotation on published prices (DEC-082)
1. The vehicle picker lists vehicles with a published snapshot; a held list blocks the quote ("prices on hold").
2. The screen applies options (RSA years, Shield, insurance combo, corporate / exchange / loyalty) to the snapshot.
3. On save the server re-fetches the prices and **refuses** a held list, a broken discount gate (Group A INV / INV_OE
   scheme > total CN) or a wrong TCS; it stores the pricing in `standard_data.pricing`.
4. Approval of a quotation is still the legacy flow (`QuoteAction` rows: approve / reject / revise). **No Sales flow
   calls the approval engine yet** (`workflows/approvals.md`) — wiring it is future work.

## Rules that bite
- **Data scoping (DEC-071):** enquiries / quotations / leads are filtered by the user's scope (branch, location,
  segment, model, variant); jobs must not depend on a user scope.
- Quotations store the **enquiry id** in `enquiry_no` (BUG-192 history).
- Booking permissions are per step: `SLS_BKNG_KYC`, `SLS_BKNG_DMS`, `SLS_BKNG_RTO`, `SLS_BKNG_REFUND`, …
- Open booking-team bugs: BUG-095 (hard-coded user ids), BUG-101, BUG-122, BUG-191 — see `docs/bugs/open.md`.

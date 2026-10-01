# Changes to the booking team's code — numbered log (share with the booking team)

**Why these changes:** the project rule DEC-093 (01-10-2026, owner): no code may query the database through the `DB`
facade (`DB::table()`, `DB::raw()`, `DB::select()` …); every read and write goes through an Eloquent model. Owner
instruction 01-10: bring the booking code (merged from `stage`) up to the project level and log every change so the
booking team can see what changed, where and why, and revert any single change.

**Rules followed in every change**
- **Behaviour stays the same.** The same rows, columns, order and totals are returned. Where the old raw query also
  read soft-deleted rows or rows outside the user's data scope, the model query keeps that (`withTrashed()`,
  `withoutGlobalScopes()`), and the reason is noted in the entry. Plain result objects are kept (`->toBase()`) where
  the views read them as plain objects.
- **One numbered change = one commit.** The commit subject starts with the number (`refactor(booking): BT-007 …`).
- **Checked before and after.** Each entry lists the screens / AJAX calls that reach the changed code. They are
  requested as superadmin and as a scoped user (id 40) on the test copy (`xlrm_testing`), before and after the edit,
  inside a rolled-back transaction. Status codes and response bodies (with CSRF tokens and times removed) are
  compared, and the entry records the result. The related automated tests are run too.
  Tool: `DB_DATABASE=xlrm_testing php artisan dev:route-snapshot 1,40 <spec> after.json --compare=before.json`
  (`app/Console/Commands/RouteSnapshot.php`; the full booking spec is `tests/RouteSnapshots/booking-all.txt`).

**To revert one change:** `git revert $(git log --format=%h --grep="BT-007")` (use the entry's number). Each change
touches only the lines listed, so a revert does not affect the others. Changes that depend on an earlier one say so.

| # | Where (file · method) | What changed | Why | Checked | Commit |
|---|---|---|---|---|---|
| BT-001 | `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php` · `liveNotInvoiced()` (trade-advance statement per row), `getDoAmount()`, `getTAStatement()` | Financier-statement lookups by DO number read through the `FinancerStatement` model | DEC-093 (no `DB::` queries). New model `App\Models\Module\Finance\FinancerStatement` (table `xlr8_financer_statement`). | OTF form (`sales/booking/otf-form`), `get-do-amount` (existing DO, unknown DO, empty), `get-ta-statement` (existing, `0`, unknown) as superadmin + user 40: 14 / 14 identical; booking tests 63 passed | `git log --grep=BT-001` |
| BT-002 | `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php` · `getAccessoriesList()` (unused helper), `getConsultantDetails()`, `delivered()`, `pendingDeliveries()`, `setupUpdateOperation()` (consultant fallback), `addAmountForm()`, `pendingEdit()`, `dealerInvoice()`, `pendingRto()` | Single-table lookups (accessory name, consultant, delivered / RTO-done ids, person / employee fallback, variant colour rows) read through their models | DEC-093. Models `Accessory`, `Employee` + `Person`, `XlDelivery`, `XlRto`, `Variant`. The raw queries read every row, so the model queries keep that: `withTrashed()` (soft-deleted rows included) and, for `XlDelivery` / `XlRto` (which carry the automatic data scope), `withoutGlobalScopes()`. | delivered, delivered/list, pending-rto, pending-deliveries, otf-form, and for one booking per status (7): edit, add-amount, pending-edit, dealer-invoice, otf-form/{id} — superadmin + user 40: 80 / 80 identical; Sales tests 74 passed; PHPStan no new errors | `git log --grep=BT-002` |
| BT-003 | `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php` · `getBaseQuery()` (the base of ~30 list tabs), `liveOrderCounts()`, `preloadGridLookups()`, `mapBookingForGrid()`; new private `onEnquiryReference()` | Booking grid queries without the DB facade: enquiry-reference join as one helper, computed columns via selectRaw, counts / names through the models | DEC-093. The booking ↔ enquiry match (id, `XENQ-{id}`, enquiry no., quick enquiry no., BINARY) was written three times with `orOn(DB::raw(…))`; it is now one helper using `orWhereRaw` (same SQL). Booking counts and person names keep the raw behaviour: `Booking::withoutGlobalScopes()` (every booking, not the user's data scope; `deleted_at` filter written out as before), `Person::withTrashed()`, `toBase()` (no model accessors). | all 41 list tabs (`tests/RouteSnapshots/booking-lists.txt`) as superadmin + user 40: 82 / 82 identical; per-booking pages (edit, add-amount, pending-edit, dealer-invoice, otf-form/{id} × 7 statuses): 80 / 80 identical; Sales tests 74 passed; PHPStan no new errors | `git log --grep=BT-003` |
| BT-004 | `app/Http/Controllers/Admin/Sales/Quotation/QuotationCrudController.php` · `index()`, `create()`, `edit()`, `history()`, `historyPdf()`, `preview()` | Quotation screens read segment / model / variant / colour names and the booking map through the models | DEC-093. 21 name lookups by code (`DB::table('xlr8_vehicle_segment|model|variant|color')->where(…)->first()`) and the quotation → booking map. The raw reads included soft-deleted rows and every booking, so: `Segment` / `VehicleModel` / `Variant` / `Color` `::withTrashed()`, `Booking::withoutGlobalScopes()`, and `toBase()` for the plain row objects the views read. | quotation list, edit, history, history PDF v1 + v2, preview, create (`?id=60923`, `?id=XENQ-60923`) as superadmin + user 40 with a rolled-back fixture quotation (no quotations exist in either database yet; `--setup=tests/RouteSnapshots/quotation-fixture.php`): 16 / 16 identical; Sales tests passed; PHPStan no new errors | `git log --grep=BT-004` |
| BT-005 | `app/Http/Controllers/Admin/Sales/Enquiry/EnquiryCrudController.php` · `getBaseQuery()` (OTF list), `resolveVehicleFromOemCode()`, `getLatestCreFups()`, `edit()`, `showEnquiry()`, `saveCreFup()`, `exchangeEnquiryEdit()` / `View()` / `Update()`, `financeEnquiryEdit()` / `View()` / `Update()`, `showOtf()` | Enquiry screens and follow-up writes through models (OTF bookings, CRE follow-ups, enquiry follow-ups, finance / exchange remarks, variant rows) | DEC-093. New models `CRM\OtfBooking` (`xlr8_crm_booking`), `CRM\CreFollowup` (`xlr8_cre_enquiry_fup`), `CRM\FinanceExchangeFollowup` (`xlr8_finexch_fup`); existing `CRM\EnquiryFollowup`, `Vehicle\Variant`. Reads keep every row as the raw queries did (`withTrashed()` / `withoutGlobalScopes()`, `toBase()` plain rows); the OPEN_FOLLOW_UP clean-up stays a hard delete (`withTrashed()->forceDelete()`, not a soft delete); inserts use the model's `query()->insert()` with the same columns (no model events, as before). | enquiry list, OTF list + OTF detail, enquiry edit / view, exchange + finance edit / view, and the grid data of 9 list types (+ a search) as superadmin + user 40, with a rolled-back fixture adding CRE follow-ups and finance / exchange remarks (`tests/RouteSnapshots/enquiry.txt`, `enquiry-fixture.php`): 38 / 38 identical (the enquiry view 500s before and after — BUG-226); new `tests/Feature/Sales/EnquiryFollowupWritesTest` (CRE follow-up save with placeholder hard-delete, count and deviation; finance / exchange remark numbering) passes before and after; enquiry flow tests passed; PHPStan no new errors | `git log --grep=BT-005` |
| BT-006 | `app/Http/Controllers/Admin/Accounts/JournalVoucher/JournalVoucherCrudController.php` · `index()`, `generateVoucherNumber()`; `app/Http/Controllers/Admin/Accounts/Receipt/ReceiptCrudController.php` · `index()`, `generateReceiptNumber()` | Journal-voucher / receipt lists and their number sequences read through the models | DEC-093. The customer-name lookup reads every enquiry (`Enquiry::query()->withoutGlobalScopes()`), and the number sequence keeps seeing every row of its series — soft-deleted ones and rows outside the user's data scope — under `lockForUpdate()` (`Bookingamount::query()->withoutGlobalScopes()`), so two users can never get the same number. `toBase()` keeps the plain rows. | journal-voucher list / create / edit, receipt list / create / show / edit / browser-print, as superadmin + user 40 with a rolled-back fixture adding a receipt and a voucher (the test copy has none; `tests/RouteSnapshots/accounts-comms.txt`, `accounts-comms-fixture.php`): identical (the receipt PDF `print` differs between two runs of unchanged code — DomPDF stamps the creation time — and is checked through `browser-print`); new `tests/Feature/Sales/AccountsNumberingTest` (series continues from a soft-deleted, out-of-scope row; new series starts at 01) passes before and after; PHPStan no new errors | `git log --grep=BT-006` |
| BT-007 | `app/Models/CRM/Enquiry.php` · `scopeMainListing()` (CRE follow-up `whereExists`) | Main enquiry list scope: `select(DB::raw(1))` → `selectRaw('1')` | DEC-093. Same SQL: the generated query of `Enquiry::query()->mainListing()` has the same hash before and after (`… exists (select 1 from xlr8_cre_enquiry_fup …)`). | SQL of the scope compared before / after (identical); enquiry flow tests passed; the default enquiry grid (`grid-data list_type=all`) is part of `tests/RouteSnapshots/enquiry.txt` | `git log --grep=BT-007` |
| BT-008 | `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php` · `PayoutEdit()`, `financeView()`; `resources/lang/en/booking.php` (`flash.finance_record_missing`) | Finance view / payout edit of a booking without a finance record go back to the finance list with a message (was a 500) | BUG-223, DEC-095 #10 (owner: fix). Both pages need the booking's finance record (86 reads of it in the two views); the finance lists only link bookings that have one, so the crash came from direct links. A guard in the controller is safer than making every read null-safe (a payout must not be saved without finance). | finance view + payout edit for one booking per status (7) as superadmin + user 40: the 4 bookings with finance render byte-identically to the committed code (superadmin's finance/5/view was re-rendered on the committed controller to confirm); the 3 without finance went 500 → 302 to `sales/booking/finance` with the message; new `BookingBugFixesTest::test_finance_pages_without_a_finance_record_return_to_the_list`; lang test passed; PHPStan no new errors | `git log --grep=BT-008` |

## Entries

<!-- entries are appended below, newest last -->

### BT-001 — Financier-statement lookups by DO number read through the `FinancerStatement` model
- **Where:** `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php` · `liveNotInvoiced()` (trade-advance statement per row), `getDoAmount()`, `getTAStatement()`
- **Why:** DEC-093 (no `DB::` queries). New model `App\Models\Module\Finance\FinancerStatement` (table `xlr8_financer_statement`).
- **Details (before → after):**
  - `DB::table('xlr8_financer_statement')->where('do_no', …)->whereNull('deleted_at')->orderByDesc('created_at')->first()`
    → `FinancerStatement::query()->where('do_no', …)->orderByDesc('created_at')->toBase()->first()` (3 places; the model's
    soft-delete scope replaces `whereNull('deleted_at')`; `toBase()` keeps the plain row object the code reads).
  - `use App\Models\Module\Finance\FinancerStatement;` added.
- **Depends on:** none
- **Checked:** OTF form (`sales/booking/otf-form`), `get-do-amount` (existing DO, unknown DO, empty), `get-ta-statement` (existing, `0`, unknown) as superadmin + user 40: 14 / 14 identical; booking tests 63 passed
- **Revert:** `git revert $(git log --format=%h --grep="BT-001")`

### BT-002 — Single-table lookups (accessory name, consultant, delivered / RTO-done ids, person / employee fallback, variant colour rows) read through their models
- **Where:** `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php` · `getAccessoriesList()` (unused helper), `getConsultantDetails()`, `delivered()`, `pendingDeliveries()`, `setupUpdateOperation()` (consultant fallback), `addAmountForm()`, `pendingEdit()`, `dealerInvoice()`, `pendingRto()`
- **Why:** DEC-093. Models `Accessory`, `Employee` + `Person`, `XlDelivery`, `XlRto`, `Variant`. The raw queries read every row, so the model queries keep that: `withTrashed()` (soft-deleted rows included) and, for `XlDelivery` / `XlRto` (which carry the automatic data scope), `withoutGlobalScopes()`.
- **Details (before → after):**
  - accessory name: `DB::table('xlr8_vehicle_accessories')->where('part_no', …)->first()` → `Accessory::withTrashed()->where(…)->toBase()->first()` (in `getAccessoriesList()`, which nothing calls — converted, not removed).
  - consultant: `DB::table('xlr8_admin_employee as e')->join('xlr8_admin_person as p', …)` → `Employee::withoutGlobalScopes()->from('xlr8_admin_employee as e')->join('xlr8_admin_person as p', …)->…->toBase()->first()`.
  - delivered ids (2 places): `DB::table('xlr8_booking_delivered')->where('status', 1)->pluck('bid')` → `XlDelivery::withoutGlobalScopes()->where('status', 1)->pluck('bid')`.
  - RTO-done ids: `DB::table('xlr8_booking_rto')->where('status', 2)->pluck('bid')` → `XlRto::withoutGlobalScopes()->…`.
  - consultant fallback: `DB::table('xlr8_admin_person' / 'xlr8_admin_employee')->where('person_code', …)->first()` → `Person::withTrashed()` / `Employee::withTrashed()` `->…->toBase()->first()`.
  - variant colour rows (3 places): `DB::table('xlr8_vehicle_variant')->where('code', …)->get([...])` → `Variant::withTrashed()->where(…)->toBase()->get([...])`.
  - imports `Employee`, `Person` added (Pint also sorted BT-001's import into place).
- **Depends on:** none
- **Checked:** delivered, delivered/list, pending-rto, pending-deliveries, otf-form, and for one booking per status (7): edit, add-amount, pending-edit, dealer-invoice, otf-form/{id} — superadmin + user 40: 80 / 80 identical; Sales tests 74 passed; PHPStan no new errors
- **Revert:** `git revert $(git log --format=%h --grep="BT-002")`

### BT-003 — Booking grid queries without the DB facade: enquiry-reference join as one helper, computed columns via selectRaw, counts / names through the models
- **Where:** `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php` · `getBaseQuery()` (the base of ~30 list tabs), `liveOrderCounts()`, `preloadGridLookups()`, `mapBookingForGrid()`; new private `onEnquiryReference()`
- **Why:** DEC-093. The booking ↔ enquiry match (id, `XENQ-{id}`, enquiry no., quick enquiry no., BINARY) was written three times with `orOn(DB::raw(…))`; it is now one helper using `orWhereRaw` (same SQL). Booking counts and person names keep the raw behaviour: `Booking::withoutGlobalScopes()` (every booking, not the user's data scope; `deleted_at` filter written out as before), `Person::withTrashed()`, `toBase()` (no model accessors).
- **Details (before → after):**
  - `getBaseQuery()`: `leftJoin('xlr8_crm_enquiries as enq', function ($join) { $join->on(…)->orOn(DB::raw("BINARY CONCAT('XENQ-', enq.id)"), '=', DB::raw('BINARY bookings.enq_no'))->orOn(…)->orOn(…); })`
    → `leftJoin('xlr8_crm_enquiries as enq', fn (JoinClause $join) => $this->onEnquiryReference($join, 'enq', 'bookings'))`.
  - `getBaseQuery()`: `DB::raw('NULL as accessories')`, `…apack_amount`, `…location_other`, `…vehicle_oem_code` in the select list
    → one `->selectRaw('NULL as accessories, NULL as apack_amount, NULL as location_other, NULL as vehicle_oem_code')`.
  - `getBaseQuery()`: refund join `on('bookings.id', '=', DB::raw('CAST(ref.entity_id AS UNSIGNED)'))` → `whereRaw('bookings.id = CAST(ref.entity_id AS UNSIGNED)')`.
  - `getBaseQuery()`: `DB::raw('COALESCE(f.fin_mode, enq.fin_mode) as fin_mode')`, `…financier` → `->selectRaw('COALESCE(…) as fin_mode, COALESCE(…) as financier')`.
  - `liveOrderCounts()` / per-row live count in `mapBookingForGrid()`: `DB::table('xlr8_booking_master as b')->join(… orOn(DB::raw …))` → `Booking::withoutGlobalScopes()->from('xlr8_booking_master as b')->join('xlr8_crm_enquiries as e', fn … onEnquiryReference($join, 'e', 'b'))`; `get([DB::raw('e.model_code as m'), …])` → `selectRaw('e.model_code as m, …, COUNT(*) as n')->toBase()->get()`.
  - `preloadGridLookups()`: consultants `DB::table('xlr8_admin_employee as e')->join(person)` → `Employee::withoutGlobalScopes()->from('xlr8_admin_employee as e')->join(…)->toBase()->pluck(…)`; stock counts `select('model_code', DB::raw('COUNT(*) as cnt'))` → `selectRaw('model_code, COUNT(*) as cnt')`; person names `DB::table('xlr8_admin_person')` → `Person::withTrashed()->…->toBase()->pluck(…)`.
  - `mapBookingForGrid()` consultant fallback: `DB::table('xlr8_admin_person')->…->value('display_name')` → `Person::withTrashed()->…->toBase()->value('display_name')`.
  - import `Illuminate\Database\Query\JoinClause` added.
- **Depends on:** BT-002 (imports `Employee`, `Person`)
- **Checked:** all 41 list tabs (`tests/RouteSnapshots/booking-lists.txt`) as superadmin + user 40: 82 / 82 identical; per-booking pages (edit, add-amount, pending-edit, dealer-invoice, otf-form/{id} × 7 statuses): 80 / 80 identical; Sales tests 74 passed; PHPStan no new errors
- **Revert:** `git revert $(git log --format=%h --grep="BT-003")`

### BT-004 — Quotation screens read segment / model / variant / colour names and the booking map through the models
- **Where:** `app/Http/Controllers/Admin/Sales/Quotation/QuotationCrudController.php` · `index()`, `create()`, `edit()`, `history()`, `historyPdf()`, `preview()`
- **Why:** DEC-093. 21 name lookups by code (`DB::table('xlr8_vehicle_segment|model|variant|color')->where(…)->first()`) and the quotation → booking map. The raw reads included soft-deleted rows and every booking, so: `Segment` / `VehicleModel` / `Variant` / `Color` `::withTrashed()`, `Booking::withoutGlobalScopes()`, and `toBase()` for the plain row objects the views read.
- **Details (before → after):**
  - `DB::table('xlr8_vehicle_segment')->where('code', …)->first()` → `Segment::withTrashed()->where('code', …)->toBase()->first()`
    (and the same for `xlr8_vehicle_model` → `VehicleModel`, `xlr8_vehicle_variant` → `Variant`, `xlr8_vehicle_color` →
    `Color`; 21 places in `index()`, `create()`, `edit()`, `history()`, `historyPdf()`, `preview()`).
  - `index()`: `DB::table('xlr8_booking_master')->whereNotNull('quotation_id')->pluck('id', 'quotation_id')`
    → `Booking::withoutGlobalScopes()->whereNotNull('quotation_id')->toBase()->pluck('id', 'quotation_id')`.
  - imports `Color`, `Segment`, `Variant`, `VehicleModel` added.
  - Observation (unchanged): `create?id=XENQ-60923` answers 404 before and after — the create screen does not accept the
    `XENQ-` reference form; `QuoteAction` lists a `revision` attribute the table does not have (nothing writes it).
- **Depends on:** none
- **Checked:** quotation list, edit, history, history PDF v1 + v2, preview, create (`?id=60923`, `?id=XENQ-60923`) as superadmin + user 40 with a rolled-back fixture quotation (no quotations exist in either database yet; `--setup=tests/RouteSnapshots/quotation-fixture.php`): 16 / 16 identical; Sales tests passed; PHPStan no new errors
- **Revert:** `git revert $(git log --format=%h --grep="BT-004")`

### BT-005 — Enquiry screens and follow-up writes through models (OTF bookings, CRE follow-ups, enquiry follow-ups, finance / exchange remarks, variant rows)
- **Where:** `app/Http/Controllers/Admin/Sales/Enquiry/EnquiryCrudController.php` · `getBaseQuery()` (OTF list), `resolveVehicleFromOemCode()`, `getLatestCreFups()`, `edit()`, `showEnquiry()`, `saveCreFup()`, `exchangeEnquiryEdit()` / `View()` / `Update()`, `financeEnquiryEdit()` / `View()` / `Update()`, `showOtf()`
- **Why:** DEC-093. New models `CRM\OtfBooking` (`xlr8_crm_booking`), `CRM\CreFollowup` (`xlr8_cre_enquiry_fup`), `CRM\FinanceExchangeFollowup` (`xlr8_finexch_fup`); existing `CRM\EnquiryFollowup`, `Vehicle\Variant`. Reads keep every row as the raw queries did (`withTrashed()` / `withoutGlobalScopes()`, `toBase()` plain rows); the OPEN_FOLLOW_UP clean-up stays a hard delete (`withTrashed()->forceDelete()`, not a soft delete); inserts use the model's `query()->insert()` with the same columns (no model events, as before).
- **Details (before → after):**
  - OTF list: `DB::table('xlr8_crm_booking as crm_booking')->select([...])->where('crm_booking.is_active', 1)` →
    `OtfBooking::withoutGlobalScopes()->from('xlr8_crm_booking as crm_booking')->select([...])->where(…)->toBase()` (still a plain query builder for its callers).
  - `showOtf()`: `DB::table('xlr8_crm_booking')->where('id', $id)->first()` → `OtfBooking::withTrashed()->where('id', $id)->toBase()->first()`.
  - `resolveVehicleFromOemCode()`: `DB::table('xlr8_vehicle_variant')->…->get([...])` → `Variant::withTrashed()->…->toBase()->get([...])`.
  - `getLatestCreFups()`, `edit()`, `showEnquiry()`: `DB::table('xlr8_cre_enquiry_fup')` → `CreFollowup::withTrashed()…->toBase()`;
    `DB::table('xlr8_crm_enquiries_fup')` → `EnquiryFollowup::withTrashed()…->toBase()`; `DB::table('xlr8_finexch_fup')` → `FinanceExchangeFollowup::withTrashed()…->toBase()`.
  - `saveCreFup()`: placeholder clean-up `DB::table(…)->delete()` → `CreFollowup::withTrashed()->…->forceDelete()` (hard delete, as before);
    last follow-up → `CreFollowup::withTrashed()->…->toBase()->first()`; `DB::table(…)->insert([...])` → `CreFollowup::query()->insert([...])` (same columns).
  - finance / exchange edit, view and update: `DB::table('xlr8_finexch_fup')` reads → `FinanceExchangeFollowup::withTrashed()…->toBase()`,
    insert → `FinanceExchangeFollowup::query()->insert([...])`.
  - imports `CreFollowup`, `EnquiryFollowup`, `FinanceExchangeFollowup`, `OtfBooking`, `Variant` added.
  - Found while checking (unchanged, logged): **BUG-226** — the enquiry view page 500s for any enquiry with a CRE follow-up (`cre_lost_reason`).
- **Depends on:** none
- **Checked:** enquiry list, OTF list + OTF detail, enquiry edit / view, exchange + finance edit / view, and the grid data of 9 list types (+ a search) as superadmin + user 40, with a rolled-back fixture adding CRE follow-ups and finance / exchange remarks (`tests/RouteSnapshots/enquiry.txt`, `enquiry-fixture.php`): 38 / 38 identical (the enquiry view 500s before and after — BUG-226); new `tests/Feature/Sales/EnquiryFollowupWritesTest` (CRE follow-up save with placeholder hard-delete, count and deviation; finance / exchange remark numbering) passes before and after; enquiry flow tests passed; PHPStan no new errors
- **Revert:** `git revert $(git log --format=%h --grep="BT-005")`

### BT-006 — Journal-voucher / receipt lists and their number sequences read through the models
- **Where:** `app/Http/Controllers/Admin/Accounts/JournalVoucher/JournalVoucherCrudController.php` · `index()`, `generateVoucherNumber()`; `app/Http/Controllers/Admin/Accounts/Receipt/ReceiptCrudController.php` · `index()`, `generateReceiptNumber()`
- **Why:** DEC-093. The customer-name lookup reads every enquiry (`Enquiry::query()->withoutGlobalScopes()`), and the number sequence keeps seeing every row of its series — soft-deleted ones and rows outside the user's data scope — under `lockForUpdate()` (`Bookingamount::query()->withoutGlobalScopes()`), so two users can never get the same number. `toBase()` keeps the plain rows.
- **Details (before → after):**
  - `index()` (both): `DB::table('xlr8_crm_enquiries')->whereIn('id', …)->get()->keyBy('id')` → `Enquiry::query()->withoutGlobalScopes()->whereIn('id', …)->toBase()->get()->keyBy('id')`.
  - `generateVoucherNumber()` / `generateReceiptNumber()`: `DB::table('xlr8_booking_amount')->where('type_number', 'like', …)->lockForUpdate()->orderBy('id', 'desc')->first()`
    → `Bookingamount::query()->withoutGlobalScopes()->…->lockForUpdate()->orderBy('id', 'desc')->toBase()->first()`.
  - import `App\Models\CRM\Enquiry` added to the journal-voucher controller.
- **Depends on:** none
- **Checked:** journal-voucher list / create / edit, receipt list / create / show / edit / browser-print, as superadmin + user 40 with a rolled-back fixture adding a receipt and a voucher (the test copy has none; `tests/RouteSnapshots/accounts-comms.txt`, `accounts-comms-fixture.php`): identical (the receipt PDF `print` differs between two runs of unchanged code — DomPDF stamps the creation time — and is checked through `browser-print`); new `tests/Feature/Sales/AccountsNumberingTest` (series continues from a soft-deleted, out-of-scope row; new series starts at 01) passes before and after; PHPStan no new errors
- **Revert:** `git revert $(git log --format=%h --grep="BT-006")`

### BT-007 — Main enquiry list scope: `select(DB::raw(1))` → `selectRaw('1')`
- **Where:** `app/Models/CRM/Enquiry.php` · `scopeMainListing()` (CRE follow-up `whereExists`)
- **Why:** DEC-093. Same SQL: the generated query of `Enquiry::query()->mainListing()` has the same hash before and after (`… exists (select 1 from xlr8_cre_enquiry_fup …)`).
- **Details (before → after):**
  - `$subquery->select(DB::raw(1))` → `$subquery->selectRaw('1')`; the `use Illuminate\Support\Facades\DB;` import is removed (no other use).
- **Depends on:** none
- **Checked:** SQL of the scope compared before / after (identical); enquiry flow tests passed; the default enquiry grid (`grid-data list_type=all`) is part of `tests/RouteSnapshots/enquiry.txt`
- **Revert:** `git revert $(git log --format=%h --grep="BT-007")`

### BT-008 — Finance view / payout edit of a booking without a finance record go back to the finance list with a message (was a 500)
- **Where:** `app/Http/Controllers/Admin/Sales/Booking/BookingCrudController.php` · `PayoutEdit()`, `financeView()`; `resources/lang/en/booking.php` (`flash.finance_record_missing`)
- **Why:** BUG-223, DEC-095 #10 (owner: fix). Both pages need the booking's finance record (86 reads of it in the two views); the finance lists only link bookings that have one, so the crash came from direct links. A guard in the controller is safer than making every read null-safe (a payout must not be saved without finance).
- **Details (before → after):**
  - after `resolvePayoutEditData()` / `resolveFinanceViewData()`: `if ($finance === null) { return redirect(backpack_url('sales/booking/finance'))->with('error', __('booking.flash.finance_record_missing')); }`
  - `booking.flash.finance_record_missing` = "This booking has no finance details yet."
- **Depends on:** none
- **Checked:** finance view + payout edit for one booking per status (7) as superadmin + user 40: the 4 bookings with finance render byte-identically to the committed code (superadmin's finance/5/view was re-rendered on the committed controller to confirm); the 3 without finance went 500 → 302 to `sales/booking/finance` with the message; new `BookingBugFixesTest::test_finance_pages_without_a_finance_record_return_to_the_list`; lang test passed; PHPStan no new errors
- **Revert:** `git revert $(git log --format=%h --grep="BT-008")`

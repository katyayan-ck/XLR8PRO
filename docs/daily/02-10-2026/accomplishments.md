# Accomplishments — 02-10-2026

Tasks completed today, with full details (the date-wise copy). The same entries are in `docs/todo.md` Part 2 under
`## 02-10-2026`; append every new entry to **both**.

### W15 — quotation and enquiry screens (BT-004, BT-005)

**Delivered:**
- **BT-004** — the quotation screens (list, create, edit, history, history PDF, preview) read vehicle names and the
  booking map through the models. Neither database has quotations yet, so the before / after check seeds one inside
  each rolled-back request (`dev:route-snapshot --setup=tests/RouteSnapshots/quotation-fixture.php`): 16 / 16 identical.
- **BT-005** — the enquiry screens (OTF list / detail, edit, view, finance / exchange edit / view, grid data of 9 list
  types) and the follow-up writes (CRE follow-up save, finance / exchange remarks) through models; new models
  `CRM\OtfBooking`, `CRM\CreFollowup`, `CRM\FinanceExchangeFollowup`. 38 / 38 responses identical; a new write test
  (`EnquiryFollowupWritesTest`) passes before and after (the OPEN placeholder stays a hard delete).
- **Snapshot tool** — `--setup` fixtures; requests keyed by their JSON body; CSRF skipped in-process; inline CSRF tokens,
  DD-MM-YYYY dates and error references ignored; run with `APP_DEBUG=false`.
**Found:** **BUG-226** — the enquiry view page crashes for any enquiry with a CRE follow-up (`cre_lost_reason`); added
to decision #10.
**Baseline now:** 271 `DB::` uses in 43 files. **Booking team's code:** done except the importers (phase 5),
the reports (D23) and uncalled helpers (deletion list).

### W15 — the unblocked part of DEC-093 done (BT-006, BT-007, platform, console, importers, all tests)

**Delivered:**
- **BT-006** — journal-voucher / receipt lists and number sequences through the models; `AccountsNumberingTest` pins the
  numbering (a series continues from rows the user cannot see). **BT-007** — `Enquiry::scopeMainListing()` `selectRaw`,
  same SQL (hash compared).
- Platform: comms screens and webhook (`CommWebhookEvent` model), admin vehicle-import lookups.
- Console / importers: `data-scope:backfill` (report and `--apply` runs compared statement by statement: identical),
  `RefreshTestingDatabase` (schema builder), the legacy user importers, the role backfill seeder.
- All 24 test files off the DB facade (query counting through `QueryExecuted` events).
**Verified:** each change compared before / after (screens, SQL or tests); full suite **573 passed, 1 skipped**.
**Left (126 uses in 8 files, all blocked):** deletions (#6, D5), booking reports (D23), spares (D28), the
enquiry / sales importers (phase 5, after the formats sign-off), `ai:refresh-context` schema cards (#21 exemption).

### Owner decisions closed (02-10, DEC-095)

**Closed questions (answered on the decision sheet):** D1 / D2 / D3 (app OTP + API security — build), BUG-207 / 209
(build), D23 (rewrite the booking reports), D13 (keep the dead links as "coming soon"), D16 (permission), BUG-223–226
(fix), BUG-219 (Dummy bookings validated), D21 (keep the BEV / Personal SO rule and make it work), booking carve-out with
the conversion (yes), BUG-206 (generated person code), D25 (codes with the colour suffix + re-import), HR gaps (no
mapping — the user data is refreshed; reset command built, run on your list), D26 (mask old KYC rows), DEC-093 defaults
(confirmed, schema tooling exempt), N4 (values in Settings), Redis (yes), Playwright for E2E (yes); N2 merge only on your
prompt; F2 / F3 left open; S6 2FA not now; D14 / S15 / runbook later; UAT settings no; D28 / D4 after go-live.
**Still open:** D29 key rotation, deletion list (#6), DEC-090 `ALL`, permission grants (#32), the pricing section (#22–27).
**Next:** to-do W18a–m, in order.

### W18a / W18b — app login fixed, app API access closed (DEC-095 #1–3, #5)

**Delivered:** the mobile-app OTP login works again (it answered 500 for every number): the user is found by the
person's primary mobile, the responses carry the person's name / mobile / e-mail, the code is generated securely, and a
token's expiry no longer resets when its row changes (BUG-227, found on the way). The app's history and document
endpoints accept only registered record types and check that the user may see the record (and own the document group);
the full settings API is for settings managers only — the app keeps `/app-settings`.
**Verified:** new API tests for the login, record access and settings (23 API tests passed); PHPStan clean.
**Found:** BUG-228 — the OTP SMS is still a placeholder (e-mail only); needs the SMS vendor / DLT details.
**Tell the app team:** use `/app-settings`; history / documents take entity codes (`BOOKING`, …) or the short names.

### W18c — booking screen bugs fixed (DEC-095 #10–12; BT-008 … BT-013)

**Delivered (six numbered, separately revertable booking-team changes, `docs/booking-team-changes.md`):**
- BT-008 (BUG-223): the finance view / payout edit of a booking with no finance record return to the finance list with a
  message (was a 500).
- BT-009 (BUG-224): "View" on the Invoiced list opens the booking.
- BT-010 (BUG-225): the refund view opens (missing receipt-log data).
- BT-011 (BUG-226): the enquiry view reads the CRE lost reason from the enquiry.
- BT-012 (BUG-219): a Dummy booking is refused, with nothing saved, when the customer, branch / location, vehicle or sale
  type is missing (was a database error).
- BT-013 (BUG-101, D21): the "BEV / Personal without a DMS SO → order 3" rule fires. It uses the segment codes BEV / PV
  and the segment of the enquiry or the model; the DMS form shows the SO field for those bookings.

**Verified:**
- `BookingBugFixesTest`, `EnquiryFollowupWritesTest`, `BookingDmsServiceTest` and `BookingFlowTest` pass.
- Route snapshots before and after each change as superadmin and user 40: only the fixed screens changed.
- PHPStan clean on the touched files.

**UAT-visible:**
- The Dummy validation message.
- For BEV / PV bookings, the SO field on the DMS form and order 3 when it is left empty (owner-approved rules).

**Left:** nothing in W18c. Next is W18d (D16 whitelists → permission).

### W18d — booking approvals by permission, not user ids (DEC-095 #9, D16; BT-014)

**Delivered:** new permission `SLS_BKNG_ORDER_APPROVE` (migration, run on `xlrm` + `xlrm_testing`). Order Verification
shows Accept / Reject only to its holders, and the `order-update` action requires it. The hard-coded ids `[5, 23, 123]`
pointed at unrelated people in this database, so nobody (superadmin included) could act before. The two id lists that
did nothing (Pending Order, Pending DMS) are removed with no change in behaviour. The permission tree labels it "Order
Approval". Guide `tech-guides/modules/sales-booking.md` updated (also the BT-012 / BT-013 rules and the order codes).
**Verified:**
- New `BookingBugFixesTest` case; 17 booking tests pass; IAM tests pass; PHPStan clean.
- Route snapshots as superadmin and user 40: only superadmin's action cell changed.
**Owner to do:** grant `SLS_BKNG_ORDER_APPROVE` to the approving designation(s).
**Found:** BUG-229 — Accept / Reject do not match `orderUpdate()` (Accept refused; Reject recorded as "hold released").
Needs the owner's rule for Reject.

### W18e — menu items without a screen show "coming soon" (DEC-095 #8, D13; BUG-056 / 062)

**Delivered:** one admin page, `/admin/coming-soon?feature=…`, names the feature and links back to the dashboard. All 59
rendered menu items that led to a 404 or `#` now open it: the booking ones are BT-015; the rest are CRM, refunds,
schemes, cashier, fee collection, accounts and others. Labels and icons are unchanged. Commented-out items are untouched.
**Verified:**
- New `MenuLinksTest`: no rendered menu link lacks a route, and the page escapes its input.
- Dashboard + page as superadmin and user 40 → 200; lang tests pass.
**Left:** each item gets its real route when its screen is built (rule in `tech-guides/platform/ui-kit.md`).

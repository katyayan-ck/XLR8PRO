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

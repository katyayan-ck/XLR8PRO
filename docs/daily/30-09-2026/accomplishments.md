# Accomplishments — 30-09-2026

Tasks completed today, with full details (the date-wise copy). The same entries are in `docs/todo.md` Part 2 under
`## 30-09-2026`; append every new entry to **both**.

### 1. Owner-approved clean-up items: `/docs` login, dead files — BUG-211, BUG-212, BUG-213

**Delivered:**
- `/docs` (the developer guides) now requires the admin login and serves only `tech-guides/` (BUG-211 fixed).
- The unused `Module\Booking\XlInsurer` copy (BUG-212) and the never-registered `app/Exceptions/Handler.php` deleted.
- BUG-213 corrected and closed: the "lowercase `pricing.php`" was a false positive from my 29-09 check on a
  case-insensitive filesystem. The file is `Pricing.php` and is the live price model used by 9 services; deleting it
  would have broken pricing, so nothing was removed.
- A flaky time-dependent assertion in `ApiErrorEnvelopeTest` fixed.

**Verified:** `DocsSiteAccessTest` (guest → login redirect; signed-in → 200 from `tech-guides/`); API envelope tests
passed 3 runs in a row; the related API / pricing / booking / insurance suites, 93 passed.

**Left:** —

### 2. Pricing reference workbooks removed from `dev/admin` history

**Delivered:** the ~18 MB of workbooks / PDFs committed by mistake in `4c82d28` are gone from every commit of
`dev/admin` (16 unpushed commits rewritten); files stay on disk in `_backup/`. **Verified:** no commit touches the path;
tree identical to before. **Left:** delete `backup/dev-admin-before-rewrite-30-09` + `git gc` once confirmed.

### 3. `origin/stage` merged into `dev/admin` — both sides' work kept (DEC-087)

**Delivered:** the booking team's 27-file work (new enquiry view / exchange / finance pages, duplicate and lost lists,
list toolbars with exports, OTF / receipt / JV / import changes) is in `dev/admin`, and none of DEC-068…071 was lost
(the stage reverts are recorded as merged but not applied). 35 conflicts resolved by intent, not by side: our
architecture wins where theirs called removed code; their features win everywhere else. Two real bugs surfaced and were
fixed (BUG-214, BUG-215).

**Verified:** no conflict markers; all Blade views compile; no references to removed classes; full suite green after the
BUG-215 fix. **Left:** the booking team should create the `SALE_TYPE` / `REGISTRATION_NO_TYPE` keyword masters
(BUG-215 follow-up); pushing / merging back to `stage` waits on the owner.

### 4. Sales UI/UX pass — shared look without touching logic (to-do U1 / U2 / U5)

**Delivered:** every Sales list and form screen on the shared UI layer: one grid look, one toolbar / popover / loader
style, token colours (dark-mode safe), cached pinned libraries, toast messages instead of `alert()`, site-format
dates. 77 views changed, ~1,940 lines of duplicated per-view CSS removed. All ids, JS hooks, AJAX calls and routes are
unchanged. Guide: `tech-guides/platform/ui-kit.md` (List screens).

**Verified:** views compile; render smoke of 82 Sales screens for users 1 and 40 (no new errors; BUG-122 reports
unchanged). **Left:** a real-browser pass of the busiest forms (booking add, OTF, quotation); page-header eyebrow on
forms as they are next edited.

### 5. Booking team DB changes compared and migrated fail-safe (DEC-088)

**Delivered:** their 4-table dump compared column by column and index by index with our DB. One fail-safe migration brings
`xlr8_crm_enquiries` in line (the lost-reason columns their merged screens need, the `vh_code` rename, `idx_mobile`, the
empty `x8_enq_source` removed). The broken rollback of their earlier migration is guarded. Our newer variant schema
(DEC-073) is kept and the reason documented.

**Verified:** up / down / up on the test copy; applied locally; re-diff clean except the intended items; 109 related tests
pass. **Left:** their environments get this and our index migrations on the next deploy; `booking.sql` stays untracked in
the project root (not committed).

### 6. Colour-mode flicker fixed — BUG-216

**Delivered:** the dark / light flashing is gone: tabs now pick up another tab's colour mode once instead of bouncing it.
**Verified:** a two-tab reproduction in headless Chrome (old 300 flips in 3 s → new 1 per switch, tabs agree).
**Left:** —

### 7. Merge wrapped up and every mobile API documented — W1, W2 (to-do U11 done)

**Delivered:** the stage merge is closed out (full suite green on the final tip, temp branch deleted). The last four API
modules — notifications / alerts / messages, documents, record history and the provider webhooks — have full docs (every
parameter, rule and response) and Postman collections, so all v1 endpoints are documented. BUG-217 (bad sort values
crashed the lists) fixed with a regression test.

**Verified:** 476 passed; the new test fails on the old code and passes now; collections parse; every documented field
was read from the controllers, resources and services. **Left:** BUG-182 (documents / history access) and the null names
wait on D3 / D1.

### 8. My Account redesigned with a Permissions & scope section — W8

**Delivered:** My Account now looks like the UI demo (profile header + side-menu settings card) and has a Permissions &
scope section listing employee code, OEM Mile ID, designation, the primary and add-on departments / divisions / branches /
locations, segments, sub-segments, models, variants, verticals and the permissions by module — blank ("—") where nothing
is defined. **Verified:** `MyAccountTest` (5 passed, every field asserted); rendered for a super admin and a scoped user.
**Left:** —

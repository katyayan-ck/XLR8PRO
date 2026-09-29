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

### 9. Vehicle Info workbook with master dropdowns — W9

**Delivered:** the Vehicle Info export now carries a dropdown on every lookup column, filled from the masters (codes),
with Sub Segment limited to the chosen Segment and number ranges on Seating / Wheels / GST%. The import only accepts
values that exist in the masters — a typo can no longer create a new segment or sub-segment, and transmission /
drivetrain are checked too. `AWD` was added to the drivetrain master because vehicles already use it.

**Verified:** 2 new tests (strict import, dropdown workbook structure); the pricing suite, 71 passed; migration run on
both databases. **Left:** duplicate keyword codes (`AUTOMATIC` / `AUTOMATIC_1` …) are collapsed in the dropdowns; the
real clean-up is F6.

### 10. Org rules enforced everywhere — W12 (DEC-089 Phase A)

**Delivered:** every new branch / department / segment gets its same-code, same-name location / division / sub-segment,
and existing gaps were filled by a fail-safe migration. Every new employee must have a primary branch, location,
department, division and a vertical; a blank location / division takes the parent's same-code child; a location must
belong to its branch and a division to its department. Legacy rows stay editable (never silently corrected); the gaps
are listed in BUG-218 for HR. The user forms mark vertical as required.

**Verified:** 6 new tests; org / IAM / import / vehicle / pricing / booking suites 340 passed; migration up / down / up on
the test copy. **Left:** filling the legacy gaps (BUG-218) — easiest once the new workbook (W10) is in.

### 11. Users workbook in the owner's layout — W10 (DEC-089 Phase B, DEC-090)

**Delivered:** Org → Users → Bulk import now exports / imports the `Users` sheet with the exact 22 headers. Single values
are master-code dropdowns (Primary Location follows the row's branch, Primary Division its department); add-on, vertical
and vehicle columns take comma-separated codes, `ALL` or `NONE` with every valid code listed on the `Lists` sheet. Each
row creates / updates the person, employee, login, role and scopes in one transaction through the entity services, and
every change of designation, primaries, manager or scopes is written to the employee history. Aadhaar is masked in the
file and kept unless a full number is typed. The old RBAC workbook stays as an audit export.

**Why:** owner request 30-09 (to-do W10); DEC-089 answers; DEC-090 for `ALL` / unchanged-cell semantics (no silent
access changes, legacy values never block a re-import).

**Verified:** 7 new feature tests; Org / IAM suites 106 passed; phpstan clean on the new services; full export →
unchanged re-import of all 200 users on the test copy = 0 failures, 0 changes; HTTP: superadmin 200 (page + 3
downloads), scoped user 40 → 403. **Left:** W11 bulk screen on `UserRowService`; filling BUG-218 gaps with the new file.

### 12. Bulk user create / edit screen — W11 (DEC-089 Phase C)

**Delivered:** Org → Users → Bulk edit: a grid of every employee user in the workbook columns. Double-click a cell to
edit: master cells open a filter-like picker (search, tick / untick; multi-value lists start with `ALL`, end with `NONE`,
vertical has no `NONE`); lists follow the row (primary location ← branch, primary division ← department, add-on
locations ← primary + add-on branches, sub-segments ← segments, models ← sub-segments / segments), and changing a
primary branch / department clears a child that no longer belongs. "Add user" adds a row for a new employee. Save sends
only new / changed / failed rows through the same `UserRowService` as the workbook (rules, history); per-row results are
shown in place and failed rows stay marked with their messages. Filters: search, All / Changed / Failed.

**Verified:** 3 HTTP feature tests (screen, data, save order, 403); headless-Chrome run of the grid script on the 200
exported rows; HTTP smoke superadmin 200, user 40 → 403. **Not verified:** a visual pass at 390 / 768 px (the toolbar
wraps with the shared `.xl-toolbar`). **Left:** BUG-218 data gaps (HR can now fix them on this screen).

### 13. Sales / booking HTTP feature tests — W3

**Delivered:** 15 HTTP tests over the enquiry, quotation and booking write flows (list screens were already in the
smoke sweep; booking step services already had unit tests). The booking sweep proves all 25 write routes refuse a
user without Sales permissions. Found and fixed BUG-220 (mock customer names in quotation history); logged BUG-219
(dummy bookings skip base validation) for the owner.

**Verified:** Sales + quotation pricing + booking service suites 77 passed. **Left:** BUG-219 decision.

### 14. PHPStan baseline — W4

**Delivered:** the whole codebase now passes PHPStan level 5 with the legacy errors recorded in `phpstan-baseline.neon`
(2 511), so every new error fails the gate; the workflow rule says so. Two real defects fixed on the way
(AuthorizationException import, SystemSettingAudit user relation); the other "class not found" paths logged as BUG-221.

**Verified:** full `phpstan analyse` → No errors (twice, before and after the fixes). **Left:** burn the baseline down
module by module (largest: legacy admin controllers 567, booking services 475).

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

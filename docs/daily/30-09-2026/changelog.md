# Changelog — 30-09-2026

Today's changes only (the date-wise copy). The same entries are in the cumulative `docs/changelog.md` under
`## 2026-09-30`; add every new entry to **both** (`.ai/guidelines/10-workflow.md`).

## Owner-approved clean-up items (DEC-086 addendum 2): `/docs` login, dead files, BUG-211 / 212 / 213
- **`config/laradocs.php`:** `route.middleware` `['web']` → `['web', 'admin']` — guests are sent to the admin login;
  signed-in users read `tech-guides/` (BUG-211 fixed). Test: `tests/Feature/Utils/DocsSiteAccessTest.php` (2).
- **Deleted:** `app/Models/Module/Booking/XlInsurer.php` (unused duplicate, BUG-212 fixed), `app/Exceptions/Handler.php`
  (never registered; `ApiExceptionRenderer` does its job, DEC-085).
- **BUG-213 closed as not a bug:** git already tracks `app/Models/Vehicle/Pricing/Pricing.php`; the 29-09 check was fooled
  by Windows' case-insensitive paths and a broken grep escape. The class is the live price model — kept.
- **`tests/Feature/Api/ApiErrorEnvelopeTest.php`:** the exact-JSON 401 test freezes time (it failed when the clock ticked
  between the request and the assertion).
- **Bugs:** 28 open / 185 closed (BUG-211, 212, 213 moved with their details; audit rows HIGH-01 / MED-02 corrected).

## History rewrite of `dev/admin` (DEC-086 addendum 2, owner-approved)
- `git filter-branch --index-filter` over the 16 unpushed commits `4c82d28^..dev/admin` removed
  `docs/reference/XLRM-Pricing-data/` (~18 MB workbooks / PDFs) from every commit. No remote branch contained them.
- **Verified:** no commit in the range touches the path; the final tree equals the pre-rewrite tip.
- **Commit ids changed** from `4c82d28` on (e.g. `db49a10` → `a5a8c47`, `3abe70a` → `96cf3ef`, `6f98953` → `f87a013`);
  older ids quoted in these records refer to the pre-rewrite commits.
- **Backup:** local branch `backup/dev-admin-before-rewrite-30-09` (and `refs/original/…`) until the owner confirms; then
  delete them and run `git gc` to drop the objects locally.

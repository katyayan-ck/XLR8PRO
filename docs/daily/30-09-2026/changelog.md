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

## Merge `origin/stage` → `dev/admin` keeping our work (owner-approved, DEC-087)
- **How:** `git merge -s ours origin/stage` (records the merge; the 4 stage reverts of DEC-068…071 are not applied),
  then the team's own changes since the last revert (`51a36f6..origin/stage`, 27 files) re-applied with `git apply -3`
  and 35 conflict hunks resolved by hand.
- **Kept from the team:** enquiry list rebuilt on one base query (duplicate / lost lists, `IN_HOUSE` finance code), new
  enquiry / exchange / finance view pages (`view`, `exchange-view`, `finance-view`) with View buttons, lost-reason and
  finance-mode keyword lists, the enquiry resolved before the OTF consultant fallback, `jvAmount` on the OTF form,
  receipt rows linking to the printable receipt with the mode name, the Quotation header row on the OTF price table,
  the DO voucher date prefill, the list toolbars (reset, header customisation, Excel / PDF export) on the exchange and
  finance lists, receipt / journal-voucher, import-job, booking add / finance-edit / transaction-list, OTF PDF and menu
  changes.
- **Kept from ours where theirs would break:** `OrgService` instead of the removed `CommonHelper`; the price-hold guard
  (DEC-082); Docs-based amount proofs (DEC-069, no public temp copies); `recordEvent(action, summary, meta, body)` (their
  message is our body); site-date formatting; token colours; the pinned AG-Grid build; export libraries via `@basset`.
- **Fixed during the merge:** BUG-214 (OTF invoice date one day early in IST), BUG-215 (RTO rule match broken by the
  stage keyword change); the 3 new routes named (`sales.enquiry.duplicate`, `sales.enquiry.lost`, `sales.enquiry.view`).
- **Tests:** full suite 473 passed + the 3 RTO tests fixed by BUG-215 (1 known skip).

## Sales UI/UX pass (to-do U1 / U2 / U5; markup + CSS only, logic untouched)
- **Shared layer:** `public/css/xl-ui.css` gains the Sales grid look (`.xl-grid`), loader / popover / toolbar / export
  classes and the shared form bits; `public/js/xl-ui.js` gains `XL.notify()` (Noty toast; replaces `alert()`).
- **77 Sales views (`resources/views/admin/sales/**`):** grid containers `xl-grid`; popover, loader, search box and
  export icons on classes (inline `display:none` kept — scripts toggle it); `bg-white` / `bg-light` / `text-dark` /
  `text-black` / `table-light` → tokens; per-view `<style>` rules now covered by the shared CSS (and overrides of the
  standard card / focus / validation look) removed — 1,942 lines out; raw CDN tags (xlsx, jsPDF, autotable, jQuery mask /
  validation, SweetAlert2, Sortable, lightbox) → `@basset` with pinned URLs (SweetAlert2 via its explicit dist file);
  the new enquiry / finance / exchange view pages tokenised (no hex); the OTF anniversary picker value uses
  `site_date()` (its picker parses the site format); 13 `alert()` → `XL.notify()`.
- **Kept on purpose:** hex inside `@media print` (quotation / OTF paper sheets), the PDF-file icon SVG in booking show,
  per-screen token-based accents (coloured pinned-column headers), screen-specific layout rules (quotation sheet, bill
  tables).
- **Verified:** all Blade views compile; 82 parameter-free Sales screens rendered as user 1 (only the 8 BUG-122 report
  pages fail — missing tables, pre-existing) and user 40 (81 × 403 by permission, 0 errors); `@basset` serves the
  libraries from the local cache.

# Current state (keep ≤ 50 lines; update at every checkpoint)

**Branch:** `feature/integrations` (Track A integration branch). **Updated:** 27-09-2026.

**UAT (target ~30-09-2026, with the booking-team merge):**
- Scope: Org/HR/User admin plus Vehicle master and Pricing (DEC-034). Sales is owned by the other team, so we don't touch it.
- Done:
  - User bulk importer fixed, web import page (DEC-035/036).
  - Standalone HR create/edit retired; Brand, booking reports, Spares and Price List hidden (DEC-037/038).
  - In-scope smoke: 64 of 65 screens return 200/302; the only 500 was Brand, now redirected.

**Sales-area merge note (for the booking-team merge).** This branch changed these Sales files since `280d052`; check them for conflicts:
- `BookingCrudController`: `editRefund()` (refund-details edit).
- `BookingCoreService`: `store()` `dd()` replaced by log + rethrow; `update()` keeps `col_type`.
- `BookingOtfService`: VOTF branch fallback via the consultant's `primary_branch_code` (BUG-161).
- `BookingRefundService`: `applyRefundDetailsEdit()`.
- `routes/backpack/booking.php`: refund-edit route; dead routes removed.
- Migration `2026_09_26_120000_add_missing_columns_to_xlr8_booking_master` (BUG-104).
- Deleted as dead: `oldImportEnquiriesJob`, `show-invoiced.blade.php`.
- `menu_items.blade.php` Sales section: Reports and Price List hidden (Blade comments).

After the merge: run `php artisan test --compact` plus a smoke of `sales/*` as users 1 and 40, then triage.

**Track B:** paused after B0 (DEC-033). Resume from xceler8 `d9009db`.

**Waiting on the user:**
- Rotate the Google service-account key, then approve the history purge.
- `userdata.xlsx` (real PII) is tracked in git.
- Ticket intake channels.
- SLA/retention (later).
- Enabling data scoping.
- Approval scope dimensions.

**Environment:** Laragon, PHP 8.4.26 (+redis), MySQL 8.4.3, Redis + Mailpit available.
After local schema or data changes, run `php artisan testing:refresh-db --force --bin-dir="D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin"`.
Tests: 221 passed, 1 skipped.

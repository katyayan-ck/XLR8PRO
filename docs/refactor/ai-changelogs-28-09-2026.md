
# AI changelog: 28-09-2026 (Track A: helper purge + platform utilities)

## Legacy helpers removed (DEC-060)
- **Deleted:**
  - `app/Helpers/{CommonHelper,XCommonHelper,XpricingHelper,date-format}.php` (`app/Helpers/` is gone);
  - `app/Models/Admin/EmpPostAssignment.php`.
- **New:** `app/Support/helpers.php` (`site_date()`), loaded through composer `autoload.files`; the `require_once` in `AppServiceProvider` is removed.
- **Service reads added:**
  - `OrgService::branchRows/locationRows/locationsByState/serviceBranches`.
  - `VehicleService::segmentOptions/modelOptions/modelOptionsFor/variantOptions/colorOptions`.
- **Callers rewired:**
  - `BookingCrudController` (about 22 calls and the dropdown AJAX endpoints);
  - `Booking{Insurance,Rto,Finance,Exchange,Kyc}Service`;
  - `SpareRequestCrudController` (its create screen no longer crashes, BUG-030 part).
- **Behaviour:** booking colour dropdowns now list the variant's colour rows instead of the retired colour table. Everything else keeps the same shapes.
- **Models:** see DEC-060 (Post relations, Booking::segment, Variant options, unused missing imports). Also `RBACService` / `OrgService` retired-Posts paths.
- **Bugs:** BUG-180 logged.
- **Smoke:**
  - As user 1: the booking models/variants/colors/branch-locations/locations endpoints, booking create/edit, the insurance/rto/finance/exchange edits and spares create all return 200.
  - As user 40: 403.
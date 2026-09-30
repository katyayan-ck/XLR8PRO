# Changelog — 01-10-2026

Today's changes only (the date-wise copy). The same entries are in the cumulative `docs/changelog.md` under
`## 2026-10-01`; add every new entry to **both** (`.ai/guidelines/10-workflow.md`).

## W14 Phase 2 — vehicle content screens (DEC-092)
- **New:** `app/Services/Vehicle/Content/VehicleContentService.php`, `app/Http/Controllers/Admin/Vehicle/Content/VehicleContentController.php`,
  12 routes `vehicle.content.*` (`routes/backpack/core.php`), views `admin/vehicle/content/{index,model,trim}.blade.php`,
  menu "Vehicle Content" under Vehicles Info (`VEH_CONT_VIEW`; the dropdown also opens for it), lang `vehicle.flash.*` (5).
- **Behaviour:** specifications per model and features per trim edited in place (blank clears), new master items from
  the page, model images (many) + PDF brochure (one), trim gallery with the level choice (all colours / one colour),
  current files with Remove (ownership checked), refused files reported on the form.
- **Tests:** `tests/Feature/Vehicle/VehicleContentScreensTest.php` (6); Vehicle suite 18 passed. Smoke: superadmin 200 on
  the three pages, user 40 → 403.

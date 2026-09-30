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

## W14 Phase 3 — specifications / features workbooks (DEC-092)
- **New** `app/Services/Vehicle/Content/VehicleContentWorkbookService.php`; controller `export()` / `import()` + routes
  `vehicle.content.export`, `vehicle.content.import`; Workbooks card on `admin/vehicle/content/index.blade.php` (exports,
  import with the kind choice, match report); lang `vehicle.flash.content_imported`.
- **Formats:** ours (codes; round-trips) and the owner's sample format (by name). Dry run of the owner's samples on the
  test copy (rolled back): specifications 765 values / 54 new items / 11 model columns not matched; features 4 571 values
  / 274 new items / 115 columns not matched (names that differ from ours — listed by the report).
- **Tests:** `tests/Feature/Vehicle/VehicleContentWorkbookTest.php` (4); Vehicle suite 22 passed.

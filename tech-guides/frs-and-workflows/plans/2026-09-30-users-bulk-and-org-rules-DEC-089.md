# Plan: users bulk export / import, bulk create / edit screen, org rules (DEC-089)

> **Status (30-09-2026): 🟡 in progress** — Phase A ✅ (org rules); Phase B (workbook) next. To-do rows W10, W11, W12. Owner answers recorded below.

## Context
The owner asked (30-09) for a users workbook with fixed headers, master-fed dropdowns, dependent lists and multi-select
add-ons (`All` first, `None` last), employee history kept, a bulk create / edit screen with the same rules, and org rules
enforced everywhere. The current users workbook (DEC-040: `UserRbacExportService`, `UserRbacWorkbookExport`,
`StandaloneUsersImport`) already has dropdowns from the masters, dependent lists (`INDIRECT`) and a one-row-per-scope
`User_Scopes` sheet; the redesign keeps its write path (entity services) and replaces its layout.

## Owner decisions (30-09)
1. **Multi-select in Excel:** comma-separated codes in the cell, a helper sheet listing the valid codes per column, every
   code validated on import. The filter-like check / uncheck picker (with `All` / `None` and dependent child lists) is in
   the bulk screen (W11).
2. **Blank vs None:** a blank add-on / vehicle-scope cell keeps what is stored; `None` clears that type; `All` grants all.
   On create, a blank vehicle scope (segment / sub-segment / model) means all.
3. **Same-name child:** every Branch / Department / Segment has a Location / Division / Sub-segment with the same code and
   name — created automatically for new parents and by a fail-safe migration for existing gaps.
4. **Aadhaar:** exported masked (last 4); on import a masked or blank value keeps the stored number, a full 12-digit
   value replaces it.

## Rules (W12, enforced by the services, the workbook and the screen)
- Every user has an employee code; OEM Mile ID optional (FSCs).
- Primary branch, location, department, division required; `All` / `None` not allowed for primaries; the primary
  location belongs to the primary branch, the primary division to the primary department.
- Vertical required (one or more; `None` not offered).
- Add-on branches / locations / departments / divisions: add-on locations offered = the primary branch's other locations
  + every location of the add-on branches (same for divisions ← departments).
- Segment / sub-segment / model blank = all (vehicle scope unrestricted).

## Workbook (W10) — sheet `Users`
Headers (in order): `Emp Code*, Employee Name*, Personal Mail Id, Official Mail ID, Personal Contact Number*, Official
Contact Number, OEM Mile ID, Aadhaar No, Primary Branch*, Addon Branch, Primary Location*, AddOn Location, Primary
Department*, Addon Department, Primary Division, Add On Divisions, Designation*, Vertical, Segment, Sub Segment, Models,
Reporting Manager`.
- Single-value columns are dropdowns (codes); Primary Location depends on Primary Branch, Primary Division on Primary
  Department (named ranges per parent + `INDIRECT`).
- Multi-value columns hold comma-separated codes; `Lists` sheet shows the valid codes per column (with `ALL` / `NONE`).
- Reporting Manager = an employee code (dropdown).
- Import: per row through `EmployeeService` / `UserService` / `UserScopeService`; an employee change (designation, branch,
  location, department, division, manager) writes employee history (`EmployeeJourneyService`); row results reported.
  The old DEC-040 two-sheet file stays importable during the change-over.

## Screen (W11) — Org → Users → Bulk edit
Grid of users (the same columns); single values as Select2 dropdowns (dependent); multi-values as a filter-like picker
(search, check / uncheck, `All`, `None`, children limited as above); add rows for new users; save → the same import service
(one code path), per-row errors shown in place, history written.

## Phases
- **A — org rules:** same-name child on create (Branch → Location, Department → Division, Segment → Sub-segment) +
  migration for gaps; user rules in the entity services; tests.
- **B — workbook:** export + import service in the new layout; masked Aadhaar; history; tests.
- **C — screen:** bulk grid with pickers on top of the Phase B service; tests + smoke.

# AI changelog: 27-09-2026 (Track A, UAT hardening)

Branch `feature/integrations`. Decisions DEC-033…038 are in `docs/decisions/decision-log.md`.

## UAT scope and approach (DEC-033/034)
- Track B (xceler8) is paused after B0, at `d9009db`.
- UAT covers Org/HR/User admin plus Vehicle master and Pricing. Sales belongs to the booking team and is untouched until their merge.

## User bulk import (DEC-035/036, BUG-162)
- `StandaloneUsersImport` now:
  - reads only the `Users_Import` sheet;
  - is idempotent;
  - keeps the existing `person_code` for known employees;
  - skips rows without a name;
  - returns a summary.
- `ImportUsersCommand` detects the sheet automatically. A web page (Users → Bulk import) offers a template download.
- The broken export and history pages are gone.
- Tests: `StandaloneUsersImportTest` (generated workbook) and `UserBulkImportPageTest`.

## Retired or hidden broken screens (DEC-037/038)
- **Employee, Person Address and Person Banking:**
  - create/edit URLs redirect to the lists;
  - the row action is now "Open person";
  - the employee list offers "Bulk import users".
  - Mitigates BUG-008/020/021/154.
- **Menu:** hid Vehicle → Brand (BUG-009), Sales → Reports (BUG-122), Spares (BUG-030/031/032/116) and Price List (BUG-069).
- **Brand URLs** redirect to Segment. The dead brand writers `SegmentSheet`, `MasterDataSeeder` and `CodeGenerator` are deleted.
- **Removed:**
  - the `org-demo` page, its controller and view (BUG-049);
  - the dead `vehicle/sub-segment/segments/{brandCode}` route and its commented-out JS (BUG-010/065).
- **Tracker:** BUG-011/012/016/018 verified as already fixed. The bug index was regenerated (52 open).

## Verification
- `php artisan test --compact`: 221 passed, 1 skipped.
- Smoke of all 65 parameter-free Vehicle/Pricing/Org/IAM/Utils GET screens as user 1: all 200/302 after the Brand redirect.
- User 40 (scoped): expected 403s; retired URLs return 302.

---
description: Excel/Sheets imports and exports. Load for importer/exporter work.
paths:
  - app/Imports/**
  - app/Exports/**
  - app/Console/Commands/**
---

# Imports & exports

- Match the nearest existing importer family for the domain (ToCollection+WithHeadingRow+WithChunkReading,
  OnEachRow+SkipsOnError, WithMultipleSheets delegating to per-sheet classes, or a BaseSheetImport subclass).
- Headers are dynamic: resolve through `SheetHeaderService` (`field_code`) + `SynonymService` — never hardcode
  header strings. Run synonym resolution **before** matching Branch/Fuel/Segment/Permit values.
- Memory: load one sheet at a time, cap columns (~AZ), chunk rows; never load a whole pricing workbook.
- Handle Before/AfterImport in the importer's own `registerEvents()` (no `Imports/Listeners` classes).
- Accumulate row results as counters/arrays (inserted/updated/skipped/errors) and log via `Log`; no custom
  exception/DTO types for row errors.
- `ImportLog` casts `warnings`/`errors` to array — pass arrays, never `json_encode()` them (BUG-152).
- Google Sheets imports (Finance/Insurance/RTO) need valid service-account credentials; the key file is
  local-only (never commit it — `gscreds.json` is gitignored).
- Large exports: stream (chunked) rather than building the whole sheet in memory.
- Users workbook (DEC-040): `php artisan users:export-rbac` / Users → Bulk import → Export produces an importable file
  (`Users_Import` + `User_Scopes`, dropdowns fed by named ranges). The user importer never guesses: unknown values
  are reported (`VALUE SKIPPED`/`ROLE SKIPPED`) and keep the stored code; absent columns are left untouched.
  Keep an unchanged export's re-import a no-op (`UserRbacWorkbookTest`).

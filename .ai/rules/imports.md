---
paths:
  - 'app/Imports/**'
---

# Imports

## Import interface family
Match the nearest existing importer family for the target domain (ToCollection+WithHeadingRow+WithChunkReading, OnEachRow+WithHeadingRow+SkipsOnError, WithMultipleSheets delegating to per-sheet classes, or a plain BaseSheetImport subclass fed raw arrays) rather than introducing a new shape.

## Import event handling
Handle Before/AfterImport behavior directly in the importer's own registerEvents() method; do not add classes under Imports/Listeners.

## Import error handling
Accumulate row-level errors with plain counters/arrays (inserted/updated/skipped/errors) and log via the Log facade; don't introduce custom exception classes or error DTOs for imports.

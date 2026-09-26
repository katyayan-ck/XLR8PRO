---
name: xcelr8-import-export
description: "Use for any Excel/Sheets import or export in Xceler8: Maatwebsite Excel, PhpSpreadsheet, ImportSession, chunked/queued jobs, SheetHeaderService, SynonymService, rollback/versioning, large workbooks. Triggers: import, export, Excel, spreadsheet, bulk upload, chunk, queue job, header mapping."
---

> Ported 26-09-2026 from `.ai/_archive/2026-09-26/.ai/skills/xcelr8-import-export` (DEC-031). Current facts in
> `.ai/rules/**` win over anything below that conflicts (e.g. dead code removed on 26-09-2026,
> roles = designations, tests on `xlrm_testing`, migrations not SQL-first).

# Skill: XCELR8 Import / Export

**When to activate:** Any task involving: Excel import, Excel export, data import, bulk upload, PhpSpreadsheet, Maatwebsite Excel, ImportSession, queued jobs, chunked processing, rollback, versioning, Data Manager, 250MB file, 50 sheets.

**Keyword triggers:** import, export, Excel, spreadsheet, bulk upload, queue, job, PhpSpreadsheet, Maatwebsite, chunk, rollback, version, data manager, 250MB, large file, SheetHeaderService, SynonymService.

---

## Architecture

```
Upload (web / API)
  → Store in Spatie Media or temp disk
  → Dispatch queued ImportJob
  → Job: open ONE sheet at a time (PhpSpreadsheet)
  → SheetHeaderService: resolve headers → field_code
  → SynonymService: resolve typos → canonical codes
  → Chunk rows (100–500 per chunk)
  → Service: validate + transform + persist
  → Disconnect sheet + GC after each sheet
  → Report: success / skipped / errors per row
```

Queue worker requirement:
```bash
php artisan queue:work --timeout=1800 --tries=1
```

---

## Investigation Protocol

When investigating an import failure:

1. **Check failed_jobs table**
   ```sql
   SELECT id, queue, payload, exception, failed_at 
   FROM failed_jobs 
   ORDER BY failed_at DESC LIMIT 10;
   ```

2. **Check memory issues**
   - Is the code loading multiple sheets simultaneously?
   - Look for `$spreadsheet->getSheet()` loops without disconnect
   - Look for `->toArray()` on large ranges

3. **Check header resolution**
   - Was `SheetHeaderService` used?
   - Were headers normalized via `SynonymService` before matching?
   - Check if headers changed since last import

4. **Check scope matching**
   - Are `ANY` / blank / `ALL` / `*` scopes handled?
   - Are comma-separated values treated as union?
   - Is "more specific row wins" logic correct?

5. **Check versioning**
   - Is WEF identical? → update in-place
   - Different WEF? → expire old row, insert new

---

## Implementation Protocol

### Memory-Safe Excel Loading

```php
use PhpOffice\PhpSpreadsheet\IOFactory;

// ✅ Load ONE sheet at a time
$reader = IOFactory::createReaderForFile($filePath);
$reader->setLoadSheetsOnly([$sheetName]); // ONE sheet only
$spreadsheet = $reader->load($filePath);
$sheet = $spreadsheet->getActiveSheet();

// ✅ Chunk rows
$highestRow = $sheet->getHighestDataRow();
$chunkSize = 200;

for ($start = 2; $start <= $highestRow; $start += $chunkSize) {
    $end = min($start + $chunkSize - 1, $highestRow);
    $rows = $sheet->rangeToArray("A{$start}:AZ{$end}", null, true, true, true);
    $this->processChunk($rows);
    unset($rows);
    gc_collect_cycles();
}

// ✅ Disconnect and GC after each sheet
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);
gc_collect_cycles();
```

### Header Resolution (Never Hardcode)

```php
use App\Services\SheetHeaderService;
use App\Services\Utils\SynonymService;

$headerRow = $sheet->rangeToArray('A1:AZ1')[0];
$headerMap = app(SheetHeaderService::class)->resolveHeaders($headerRow);
// $headerMap: ['Model Code' => 'vehicle_variant_code', 'OEM Model' => 'oem_model_name', ...]

// Synonym resolution for value matching
$branchCode = app(SynonymService::class)->resolve('Branch', $rawBranchValue);
```

### Scope Matching

```php
// ANY / blank / ALL / * = matches all descendants at that level
$scopeValue = trim($row['scope'] ?? '');
if (empty($scopeValue) || in_array(strtoupper($scopeValue), ['ANY', 'ALL', '*'])) {
    $scope = null; // matches all
} else {
    $scope = array_map('trim', explode(',', $scopeValue)); // union
}

// More specific row wins — sort by specificity before applying
```

### Error Handling & Reporting

```php
// Collect errors per row — never abort entire import on single row failure
$errors = [];
$skipped = 0;
$imported = 0;

foreach ($rows as $rowNum => $row) {
    try {
        $this->importRow($row);
        $imported++;
    } catch (ValidationException $e) {
        $errors[$rowNum] = $e->errors();
        $skipped++;
    }
}

// Return structured report
return new ImportReport(
    imported: $imported,
    skipped: $skipped,
    errors: $errors,
);
```

### Rollback / Versioning Pattern

```php
// For WEF-versioned data (pricing rules)
DB::transaction(function () use ($data, $wef) {
    // Check if same WEF exists
    $existing = PricingPrice::query()
        ->where('variant_code', $data['variant_code'])
        ->where('wef', $wef)
        ->where('is_active', true)
        ->first();

    if ($existing) {
        // Same WEF → update in-place
        $existing->update($data);
    } else {
        // Different WEF → expire previous
        PricingPrice::query()
            ->where('variant_code', $data['variant_code'])
            ->where('is_active', true)
            ->update(['is_active' => false, 'expired_on' => $wef]);

        // Insert new
        PricingPrice::create(array_merge($data, ['wef' => $wef, 'is_active' => true]));
    }
});
```

---

## Export Pattern

```php
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class BookingExport implements FromQuery, WithHeadings, WithChunkReading
{
    public function chunkSize(): int
    {
        return 500; // Never dump all at once
    }

    public function query(): Builder
    {
        return Booking::query()
            ->with(['customer', 'vehicle']) // eager load, no N+1
            ->where('status', 'CONFIRMED');
    }

    public function headings(): array
    {
        return ['Booking No', 'Customer Name', 'Vehicle', 'Amount', 'Date'];
    }
}
```

---

## Location

```
app/Imports/{Module}/{Process}/           ← Import classes
app/Exports/{Module}/{Process}/           ← Export classes
app/Jobs/{Module}/{Process}/              ← Queued import/export jobs
resources/views/{module}/{process}/       ← Upload UI
```

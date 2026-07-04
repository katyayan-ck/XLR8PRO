<?php

namespace App\Imports\Sheets;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * BaseSheetImport - Enhanced with per-row detailed logging
 */
abstract class BaseSheetImport
{
    protected string $sheetName = 'Unknown';

    protected int $inserted = 0;
    protected int $updated  = 0;
    protected int $skipped  = 0;
    protected int $errors   = 0;

    protected array $errorMessages = [];
    protected array $warnings      = [];

    public function handle(array $rows, bool $dryRun = false): void
    {
        if (empty($rows)) {
            $this->logSheet("No data rows found");
            return;
        }

        $headers = null;
        $dataRows = [];

        foreach ($rows as $raw) {
            $raw = array_values((array) $raw);
            if ($headers === null) {
                $nonEmpty = array_filter($raw, fn($v) => $v !== null && trim((string)$v) !== '');
                if (empty($nonEmpty)) continue;
                $headers = array_map(fn($h) => trim((string)$h), $raw);
                continue;
            }
            $dataRows[] = $raw;
        }

        if (empty($dataRows)) {
            $this->logSheet("No data rows after header");
            return;
        }

        if ($dryRun) DB::beginTransaction();

        $this->logSheet("Starting import — {$this->sheetName} (" . count($dataRows) . " rows)");

        foreach ($dataRows as $idx => $rawValues) {
            $rowIndex = $idx + 2;
            $nonEmpty = array_filter($rawValues, fn($v) => $v !== null && trim((string)$v) !== '');
            if (empty($nonEmpty)) continue;

            $row = [];
            foreach ($headers as $i => $header) {
                if ($header !== '') $row[$header] = $rawValues[$i] ?? null;
            }

            $normalized = method_exists($this, 'normalizeRow') ? $this->normalizeRow($row) : $row;

            try {
                $this->processRow($normalized, $rowIndex);
            } catch (\Throwable $e) {
                $this->recordError($rowIndex, $e->getMessage(), $e);
            }
        }

        if ($dryRun) DB::rollBack();

        $this->logSheet("Finished — Inserted:{$this->inserted} | Updated:{$this->updated} | Skipped:{$this->skipped} | Errors:{$this->errors}");
    }

    abstract protected function processRow(array $row, int $rowIndex): void;

    protected function upsert(string $table, array $data, array $matchOn): array
    {
        $query = DB::table($table);
        foreach ($matchOn as $col => $val) $query->where($col, $val);
        $existing = $query->first();

        $now = Carbon::now();
        $data['updated_at'] = $now;

        if ($existing) {
            $updateData = $data;
            foreach (array_keys($matchOn) as $k) unset($updateData[$k]);
            DB::table($table)->where('id', $existing->id)->update($updateData);
            $this->updated++;
            return ['id' => $existing->id, 'action' => 'updated'];
        }

        $data['created_at'] = $now;
        $id = DB::table($table)->insertGetId($data);
        $this->inserted++;
        return ['id' => $id, 'action' => 'inserted'];
    }

    protected function logRow(int $rowIndex, string $status, string $message = ''): void
    {
        $logMsg = "[{$this->sheetName}] Row {$rowIndex} → {$status}";
        if ($message) $logMsg .= " | {$message}";

        echo $logMsg . PHP_EOL;
        Log::channel('import')->info($logMsg);
    }

    protected function recordError(int $rowIndex, string $message, ?\Throwable $e = null): void
    {
        $this->errors++;
        $this->logRow($rowIndex, '❌ FAIL', $message);
        $this->errorMessages[] = "[{$this->sheetName}] Row {$rowIndex}: {$message}";
        Log::channel('import')->error("[{$this->sheetName}] Row {$rowIndex}: {$message}", ['exception' => $e?->getMessage()]);
    }

    protected function logSheet(string $message): void
    {
        echo "[$this->sheetName] $message" . PHP_EOL;
        Log::channel('import')->info("[$this->sheetName] $message");
    }

    // ... (keep all your existing helpers: s(), n(), b(), code(), etc.)
    protected function s(mixed $value): string { return trim((string)($value ?? '')); }
    protected function n(mixed $value): ?string {
        $v = trim((string)($value ?? ''));
        return in_array(strtolower($v), ['', 'null', 'n/a', 'na', '-', '?'], true) ? null : $v;
    }
    protected function b(mixed $value, bool $default = false): int {
        if ($value === null) return $default ? 1 : 0;
        return in_array(strtolower(trim((string)$value)), ['1','yes','true','on','y'], true) ? 1 : 0;
    }
    protected function code(mixed $value, int $maxLen = 0): ?string {
        $v = strtoupper(trim((string)($value ?? '')));
        if (in_array($v, ['', 'NULL', 'N/A', 'NA', '-'], true)) return null;
        return $maxLen > 0 ? substr($v, 0, $maxLen) : $v;
    }

    /** Safe int with null fallback */
    protected function i(mixed $value, ?int $default = null): ?int
    {
        if ($value === null || trim((string) $value) === '') return $default;
        return (int) $value;
    }

    
    /** Split comma/semicolon-separated codes into array */
    protected function splitCodes(mixed $value): array
    {
        if (!$value) return [];
        $parts = preg_split('/[,;]+/', strtoupper((string) $value));
        return array_values(array_filter(array_map('trim', $parts)));
    }

    /**
     * Parse date from string, DateTime, Carbon, or Excel serial number.
     */
    protected function parseDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') return null;

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }

        // Excel serial number
        if (is_numeric($value) && (int) $value > 1000 && (int) $value < 100000) {
            try {
                $ts = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp((int) $value);
                return Carbon::createFromTimestamp($ts)->format('Y-m-d');
            } catch (\Throwable) {}
        }

        try {
            $parsed = Carbon::parse((string) $value);
            if ($parsed->year < 1900 || $parsed->year > 2100) return null;
            return $parsed->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function yesNo(mixed $val, bool $default = false): int
{
    if ($val === null) return $default ? 1 : 0;
    return in_array(
        strtolower(trim((string)$val)),
        ['1', 'yes', 'true', 'on', 'y']
    ) ? 1 : 0;
}
}

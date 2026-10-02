<?php

namespace App\Services\Platform\Privacy;

use App\Models\Utilities\CommHistory\CommThread;
use App\Models\Utilities\Privacy\KycMaskBackup;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Models\Audit;

/**
 * Masks the customer's Aadhaar / PAN copies kept in history rows (D26, DEC-095 #19; BUG-195 fixed new entries): the
 * booking timeline (`xlr8_utils_comm_thread.extra_data`) and the change log (`audits.old_values` / `new_values`). The
 * KYC record itself (booking `adhar_no` / `pan_no`) is not touched.
 *
 * Values are found by key (`adhar_no`, `pan_no`, any depth, also inside JSON stored as a string) — never by pattern, so
 * TRC / application / account numbers and GSTINs stay as they are. Aadhaar → `XXXXXXXX` + last 4 digits, PAN →
 * `XXXXXX` + last 4 characters (the BUG-195 format); a value that is not a well-formed Aadhaar / PAN is counted as
 * "other" and left alone. Each changed cell's original is kept encrypted in `KycMaskBackup` first, and rows are
 * written with base-query updates (no model events, so no new audit rows copy the value again).
 *
 * Example:
 *
 *   $report = app(KycHistoryMaskingService::class)->run(apply: false);   // ['cells' => 3, 'values' => [...], ...]
 */
class KycHistoryMaskingService
{
    /** Keys whose values are masked. */
    private const AADHAAR_KEYS = ['adhar_no', 'aadhaar_no', 'aadhar_no'];

    private const PAN_KEYS = ['pan_no'];

    /**
     * Scans (and with $apply masks) every history cell.
     *
     * @return array{cells: int, values: array{aadhaar: int, pan: int, other: int}, tables: array<string, int>, applied: bool}
     */
    public function run(bool $apply): array
    {
        $report = ['cells' => 0, 'values' => ['aadhaar' => 0, 'pan' => 0, 'other' => 0], 'tables' => [], 'applied' => $apply];

        foreach ($this->sources() as [$model, $columns]) {
            $table = $model->getTable();
            $model->newQuery()->withoutGlobalScopes()->toBase()->orderBy('id')->select(array_merge(['id'], $columns))
                ->chunkById(500, function ($rows) use ($table, $columns, $apply, $model, &$report) {
                    foreach ($rows as $row) {
                        foreach ($columns as $column) {
                            $raw = $row->{$column};
                            if (! is_string($raw) || ! preg_match('/adhar|aadhaar|aadhar|pan_no/i', $raw)) {
                                continue;
                            }
                            $counts = ['aadhaar' => 0, 'pan' => 0, 'other' => 0];
                            $masked = $this->maskJson($raw, $counts);
                            foreach ($counts as $kind => $n) {
                                $report['values'][$kind] += $n;
                            }
                            if ($masked === $raw) {
                                continue;
                            }
                            $report['cells']++;
                            $report['tables'][$table] = ($report['tables'][$table] ?? 0) + 1;
                            if ($apply) {
                                DB::transaction(function () use ($model, $table, $row, $column, $raw, $masked) {
                                    KycMaskBackup::query()->firstOrCreate(
                                        ['source_table' => $table, 'source_id' => $row->id, 'source_column' => $column],
                                        ['original_encrypted' => Crypt::encryptString($raw)],
                                    );
                                    $model->newQuery()->withoutGlobalScopes()->toBase()->where('id', $row->id)->update([$column => $masked]);
                                });
                            }
                        }
                    }
                });
        }

        return $report;
    }

    /**
     * Puts every backed-up original back and removes its backup row. A backup that cannot be decrypted (written under a
     * different `APP_KEY`) is left in place and counted as failed — the rest are still restored.
     *
     * @return array{restored: int, failed: int}
     */
    public function restore(): array
    {
        $models = [];
        foreach ($this->sources() as [$model]) {
            $models[$model->getTable()] = $model;
        }
        $result = ['restored' => 0, 'failed' => 0];
        KycMaskBackup::query()->orderBy('id')->chunkById(500, function ($backups) use ($models, &$result) {
            foreach ($backups as $backup) {
                $model = $models[$backup->source_table] ?? null;
                if ($model === null) {
                    continue;
                }
                try {
                    $original = Crypt::decryptString($backup->original_encrypted);
                } catch (DecryptException) {
                    $result['failed']++;
                    Log::warning('KYC mask backup not restorable with this APP_KEY', ['backup_id' => $backup->id]);

                    continue;
                }
                DB::transaction(function () use ($model, $backup, $original) {
                    $model->newQuery()->withoutGlobalScopes()->toBase()->where('id', $backup->source_id)
                        ->update([$backup->source_column => $original]);
                    $backup->delete();
                });
                $result['restored']++;
            }
        });

        return $result;
    }

    /**
     * Masks one value by its key: Aadhaar → `XXXXXXXX1234`, PAN → `XXXXXX234F`; anything else is returned unchanged.
     *
     * @param  array{aadhaar: int, pan: int, other: int}  $counts
     */
    public function maskValue(string $key, mixed $value, array &$counts): mixed
    {
        $key = strtolower($key);
        if (! is_string($value) || trim($value) === '' || str_starts_with($value, 'X')) {
            return $value;
        }
        if (in_array($key, self::AADHAAR_KEYS, true)) {
            $digits = preg_replace('/[\s-]+/', '', $value);   // also 1234-5678-9012 / 1234 5678 9012
            if (preg_match('/^\d{12}$/', (string) $digits)) {
                $counts['aadhaar']++;

                return 'XXXXXXXX'.substr((string) $digits, -4);
            }
            $counts['other']++;
        } elseif (in_array($key, self::PAN_KEYS, true)) {
            $pan = strtoupper(trim($value));
            if (preg_match('/^[A-Z]{5}\d{4}[A-Z]$/', $pan)) {
                $counts['pan']++;

                return 'XXXXXX'.substr($pan, -4);
            }
            $counts['other']++;
        }

        return $value;
    }

    /**
     * Masks a JSON document (also JSON nested as a string value); returns the input unchanged when it is not JSON or
     * holds nothing to mask.
     *
     * @param  array{aadhaar: int, pan: int, other: int}  $counts
     */
    private function maskJson(string $raw, array &$counts): string
    {
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return $raw;
        }
        $changed = false;
        $masked = $this->maskTree($data, $counts, $changed);

        return $changed ? (string) json_encode($masked, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $raw;
    }

    /**
     * @param  array<mixed>  $data
     * @param  array{aadhaar: int, pan: int, other: int}  $counts
     * @return array<mixed>
     */
    private function maskTree(array $data, array &$counts, bool &$changed): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskTree($value, $counts, $changed);
            } elseif (is_string($value) && $value !== '' && in_array($value[0], ['{', '['], true) && preg_match('/adhar|aadhaar|aadhar|pan_no/i', $value)) {
                $inner = $this->maskJson($value, $counts);
                if ($inner !== $value) {
                    $data[$key] = $inner;
                    $changed = true;
                }
            } elseif (is_string($key)) {
                $new = $this->maskValue($key, $value, $counts);
                if ($new !== $value) {
                    $data[$key] = $new;
                    $changed = true;
                }
            }
        }

        return $data;
    }

    /** @return list<array{0: Model, 1: list<string>}> history tables and their JSON columns */
    private function sources(): array
    {
        return [
            [new CommThread, ['extra_data']],
            [new Audit, ['old_values', 'new_values']],
        ];
    }
}

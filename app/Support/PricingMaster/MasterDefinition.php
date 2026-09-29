<?php

declare(strict_types=1);

namespace App\Support\PricingMaster;

use App\Services\Vehicle\Pricing\Import\PricingWorkbookReader;
use App\Support\Entity\EntityService;
use App\Support\Entity\Field;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * One pricing master screen (DEC-083): what the shared list / form / import / export of Admin → Pricing shows and how
 * it writes. Writes always go through the entity service (DEC-050); a pricing change outside the Pricing Process is
 * picked up by PricingParamObserver (automatic recalculation + sync stamp), so a definition never triggers it itself.
 *
 * Rows with a WEF are versioned like every pricing import (DEC-058): a save with the same WEF updates the row; a new WEF
 * expires the stored row at that date and inserts the new version. The default import / export is one row per record
 * ("ID" first, then the columns' labels); a definition backed by a Pricing Process workbook overrides both.
 *
 * @template TModel of Model
 */
abstract class MasterDefinition
{
    public const CHUNK = 250;

    abstract public function key(): string;

    abstract public function label(): string;

    /** Permission prefix: `{prefix}_VIEW` lists / exports, `{prefix}_MANAGE` writes / imports. */
    abstract public function permission(): string;

    /** @return class-string<TModel> */
    abstract public function model(): string;

    /**
     * Grid / export columns: `field`, `label`, `type` (text | number | date | bool), optional `width`, `pinned`.
     *
     * @return list<array{field: string, label: string, type?: string, width?: int, pinned?: string}>
     */
    abstract public function columns(): array;

    public function icon(): string
    {
        return 'la-list';
    }

    public function description(): string
    {
        return '';
    }

    /** False for masters that never change prices (accessories). */
    public function recalculates(): bool
    {
        return true;
    }

    public function service(): ?EntityService
    {
        return null;
    }

    /** Attributes every row of this master carries (e.g. `addon_type = RSA`); set on create, hidden in the form. */
    public function defaults(): array
    {
        return [];
    }

    /** @return Builder<TModel> */
    public function query(bool $history = false): Builder
    {
        $query = $this->model()::query();
        foreach ($this->defaults() as $column => $value) {
            $query->where($column, $value);
        }
        if (! $history && $this->hasColumn('is_active')) {
            $query->where('is_active', true);
        }

        return $query;
    }

    /** Form fields, in order (default: the service's fields minus defaults and bookkeeping). @return list<string> */
    public function formFields(): array
    {
        $skip = array_merge(array_keys($this->defaults()), ['import_session_id', 'is_active', 'expired_on', 'extra_json', 'created_by', 'updated_by']);

        return array_values(array_diff(array_keys($this->fieldMap()), $skip));
    }

    /**
     * How one form field renders: type (text | number | date | select | bool | textarea), label, options, required, help.
     *
     * @return array{type: string, label: string, options: list<string>, required: bool, help: string}
     */
    public function input(string $name): array
    {
        $field = $this->fieldMap()[$name] ?? null;
        $format = (string) ($field->format ?? '');
        $options = str_starts_with($format, 'One of: ') ? array_map('trim', explode(',', substr($format, 8))) : [];
        $type = match (true) {
            $options !== [] => 'select',
            (bool) $field?->boolean => 'bool',
            str_starts_with($format, 'Date') => 'date',
            str_contains(strtolower($format), 'number') || str_contains(strtolower($format), 'decimal') || str_contains(strtolower($format), 'integer') => 'number',
            default => 'text',
        };

        return ['type' => $type, 'label' => $field->label ?? ucwords(str_replace('_', ' ', $name)), 'options' => $options,
            'required' => (bool) $field?->required, 'help' => str_starts_with($format, 'Scope') || str_contains($format, 'ANY') ? 'Blank / ANY = all; comma = several.' : ''];
    }

    /** One grid row (plain values; the definition's columns plus `id`). @return array<string, mixed> */
    public function row(Model $model): array
    {
        $out = ['id' => $model->getKey()];
        foreach ($this->columns() as $column) {
            $value = data_get($model, $column['field']);
            $out[$column['field']] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        }

        return $out;
    }

    public function hasWef(): bool
    {
        return isset($this->fieldMap()['wef_date']);
    }

    /**
     * Create, update, or (a new WEF) expire + insert a new version. Returns the row that is now live.
     *
     * @param  array<string, mixed>  $input
     * @param  TModel|null  $existing
     * @return TModel
     *
     * @throws ValidationException
     */
    public function save(array $input, ?Model $existing = null): Model
    {
        $service = $this->service() ?? throw new \LogicException(static::class.' has no entity service.');
        $input = array_intersect_key($input, $this->fieldMap()) + $this->defaults();
        if ($this->hasWef() && blank($input['wef_date'] ?? null)) {
            $input['wef_date'] = $existing?->getAttribute('wef_date')?->format('Y-m-d') ?? now()->toDateString();
        }
        if (! $existing) {
            return $service->create($input);
        }
        $storedWef = $existing->getAttribute('wef_date');
        $storedWef = $storedWef instanceof \DateTimeInterface ? $storedWef->format('Y-m-d') : ($storedWef ? substr((string) $storedWef, 0, 10) : null);
        if ($this->hasWef() && $storedWef !== null && $storedWef !== substr((string) $input['wef_date'], 0, 10)) {
            return DB::transaction(function () use ($service, $existing, $input) {
                $service->update($existing, ['is_active' => false, 'expired_on' => $input['wef_date']]);
                $keep = array_diff_key(array_intersect_key($existing->getAttributes(), $this->fieldMap()),
                    array_flip(['import_session_id', 'is_active', 'expired_on', 'wef_date']));   // every stored value carries forward

                return $service->create(array_merge($keep, $input, ['is_active' => true, 'expired_on' => null]));
            });
        }

        return $service->update($existing, $input);
    }

    /** Remove a row: versioned rows are expired today, or at their WEF if later (history stays); others are soft-deleted. @param TModel $model */
    public function remove(Model $model): void
    {
        if ($this->hasWef() && $this->service()) {
            $wef = $model->getAttribute('wef_date');
            $wef = $wef instanceof \DateTimeInterface ? $wef->format('Y-m-d') : (string) $wef;
            $this->service()->update($model, ['is_active' => false, 'expired_on' => max(now()->toDateString(), $wef)]);   // a future row never goes live

            return;
        }
        $model->delete();
    }

    /** Write the export workbook; returns the rows written. */
    public function export(string $path): int
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle(mb_substr($this->sheetTitle(), 0, 31));
        $columns = $this->columns();
        $data = [array_merge(['ID'], array_column($columns, 'label'))];
        $this->query()->orderBy($this->model()::query()->getModel()->getKeyName())->chunk(1000, function ($rows) use (&$data, $columns) {
            foreach ($rows as $model) {
                $row = $this->row($model);
                $data[] = array_merge([$row['id']], array_map(fn ($c) => is_bool($row[$c['field']] ?? null) ? (int) $row[$c['field']] : ($row[$c['field']] ?? null), $columns));
            }
        });
        $sheet->fromArray($data, null, 'A1', true);
        $sheet->freezePane('B2');
        (new Xlsx($book))->save($path);

        return count($data) - 1;
    }

    /**
     * Import the default one-row-per-record sheet: a known ID updates (or versions) that row, no ID creates one.
     * Chunked, one transaction per chunk; a rejected row is reported, not fatal.
     *
     * @param  callable(int, int): void|null  $progress  rows done, rows seen so far
     * @return array{rows: int, created: int, updated: int, rejected: int, issues: list<array{row: int, reason: string}>}
     */
    public function import(string $path, ?string $wef = null, ?callable $progress = null): array
    {
        $reader = app(PricingWorkbookReader::class);
        $sheet = $reader->sheetNames($path)[0] ?? throw new \RuntimeException('The workbook has no sheet.');
        $labels = array_column($this->columns(), 'field', 'label');
        $stats = ['rows' => 0, 'created' => 0, 'updated' => 0, 'rejected' => 0, 'issues' => []];
        $map = null;
        $chunk = [];
        foreach ($reader->rows($path, $sheet) as $rowNumber => $cells) {
            if ($map === null) {
                $map = [];
                foreach ($cells as $i => $header) {
                    $header = trim((string) $header);
                    $map[$i] = strcasecmp($header, 'ID') === 0 ? 'id' : ($labels[$header] ?? null);
                }

                continue;
            }
            $chunk[$rowNumber] = $cells;
            if (count($chunk) >= self::CHUNK) {
                $this->importChunk($chunk, $map, $wef, $stats);
                $chunk = [];
                $progress && $progress($stats['rows'], $stats['rows']);
            }
        }
        if ($chunk !== []) {
            $this->importChunk($chunk, $map ?? [], $wef, $stats);
        }
        $progress && $progress($stats['rows'], $stats['rows']);

        return $stats;
    }

    /**
     * @param  array<int, list<mixed>>  $chunk
     * @param  array<int, string|null>  $map
     * @param  array{rows: int, created: int, updated: int, rejected: int, issues: list<array{row: int, reason: string}>}  $stats
     */
    protected function importChunk(array $chunk, array $map, ?string $wef, array &$stats): void
    {
        DB::transaction(function () use ($chunk, $map, $wef, &$stats) {
            foreach ($chunk as $rowNumber => $cells) {
                $stats['rows']++;
                $input = [];
                foreach ($map as $i => $field) {
                    if ($field !== null && array_key_exists($i, $cells)) {
                        $input[$field] = is_string($cells[$i]) ? trim($cells[$i]) : $cells[$i];
                    }
                }
                $id = $input['id'] ?? null;
                unset($input['id']);
                if ($wef !== null && $this->hasWef()) {
                    $input['wef_date'] = $wef;
                }
                try {
                    $existing = $id ? $this->query(true)->whereKey($id)->first() : null;
                    if ($id && ! $existing) {
                        throw new \RuntimeException("ID {$id} is not a row of this list.");
                    }
                    $this->save(array_filter($input, fn ($v) => $v !== null), $existing);
                    $stats[$existing ? 'updated' : 'created']++;
                } catch (ValidationException $e) {
                    $this->reject($stats, $rowNumber, implode(' ', array_merge(...array_values($e->errors()))));
                } catch (\Throwable $e) {
                    $this->reject($stats, $rowNumber, $e->getMessage());
                }
            }
        });
    }

    public function sheetTitle(): string
    {
        return $this->label();
    }

    /** @param array{rows: int, created: int, updated: int, rejected: int, issues: list<array{row: int, reason: string}>} $stats */
    protected function reject(array &$stats, int $row, string $reason): void
    {
        $stats['rejected']++;
        if (count($stats['issues']) < 500) {
            $stats['issues'][] = ['row' => $row, 'reason' => mb_substr($reason, 0, 300)];
        }
    }

    /** @return array<string, Field> */
    protected function fieldMap(): array
    {
        $out = [];
        foreach ($this->service()?->fields() ?? [] as $field) {
            if (! $field->virtual) {
                $out[$field->name] = $field;
            }
        }

        return $out;
    }

    protected function hasColumn(string $column): bool
    {
        return in_array($column, $this->model()::query()->getModel()->getFillable(), true);
    }
}

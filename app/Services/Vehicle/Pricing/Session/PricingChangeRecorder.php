<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Session;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records every row a pricing session inserts, updates, soft-deletes or expires (DEC-073) into
 * xlr8_vehicle_pricing_session_changes, so Discard can undo exactly that session's work and nothing else.
 *
 *   app(PricingChangeRecorder::class)->within($session->id, fn () => $importer->run(...));
 *
 * Model writes are captured by PricingChangeObserver (registered on the pricing and vehicle master models); bulk
 * expiries call captureBulk() before they update. Outside within() nothing is recorded.
 */
class PricingChangeRecorder
{
    private ?int $sessionId = null;

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function within(int $sessionId, callable $callback)
    {
        $previous = $this->sessionId;
        $this->sessionId = $sessionId;
        try {
            return $callback();
        } finally {
            $this->sessionId = $previous;
        }
    }

    public function active(): ?int
    {
        return $this->sessionId;
    }

    public function created(Model $model): void
    {
        $this->log($model->getTable(), (int) $model->getKey(), 'insert', null);
    }

    public function updated(Model $model): void
    {
        $changed = array_keys($model->getChanges());
        $changed = array_values(array_diff($changed, ['updated_at', 'updated_by']));
        if ($changed === []) {
            return;
        }
        $this->log($model->getTable(), (int) $model->getKey(), 'update', array_intersect_key($model->getOriginal(), array_flip($changed)));
    }

    public function softDeleted(Model $model): void
    {
        $this->log($model->getTable(), (int) $model->getKey(), 'soft_delete', ['deleted_at' => null, 'deleted_by' => null]);
    }

    /**
     * Record the rows a bulk update is about to change (call before running it).
     *
     * @param  Builder<Model>  $query
     * @param  list<string>  $columns  columns the update will change
     */
    public function captureBulk(Builder $query, array $columns): void
    {
        if ($this->sessionId === null) {
            return;
        }
        $table = $query->getModel()->getTable();
        $key = $query->getModel()->getKeyName();
        $rows = (clone $query)->toBase()->get(array_merge([$key], $columns));
        foreach ($rows as $row) {
            $before = (array) $row;
            unset($before[$key]);
            $this->log($table, (int) $row->{$key}, 'update', $before);
        }
    }

    /**
     * Undo a session's changes, newest first: inserted rows are removed, updated / expired / soft-deleted rows get
     * their previous values back. Returns how many changes were undone.
     */
    public function rollback(int $sessionId): int
    {
        $changes = DB::table('xlr8_vehicle_pricing_session_changes')
            ->where('import_session_id', $sessionId)->orderByDesc('id')->get();

        foreach ($changes as $change) {
            if (! Schema::hasTable($change->table_name)) {
                continue;
            }
            $row = DB::table($change->table_name)->where('id', $change->row_id);
            if ($change->action === 'insert') {
                $row->delete();   // the row did not exist before this session
            } else {
                $before = json_decode((string) $change->before, true) ?: [];
                if ($before !== []) {
                    $row->update($before);
                }
            }
        }
        DB::table('xlr8_vehicle_pricing_session_changes')->where('import_session_id', $sessionId)->delete();

        return $changes->count();
    }

    /** @param  array<string, mixed>|null  $before */
    private function log(string $table, int $rowId, string $action, ?array $before): void
    {
        if ($this->sessionId === null || $table === 'xlr8_vehicle_pricing_session_changes') {
            return;
        }
        DB::table('xlr8_vehicle_pricing_session_changes')->insert([
            'import_session_id' => $this->sessionId,
            'table_name' => $table,
            'row_id' => $rowId,
            'action' => $action,
            'before' => $before === null ? null : json_encode($this->plain($before)),
            'created_at' => now(),
            'created_by' => auth(backpack_guard_name())->id() ?? auth()->id(),
        ]);
    }

    /**
     * Stored values as the database holds them (dates → strings, arrays → JSON).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function plain(array $values): array
    {
        return array_map(fn ($v) => match (true) {
            $v instanceof \DateTimeInterface => $v->format('Y-m-d H:i:s'),
            is_array($v) => json_encode($v),
            is_bool($v) => (int) $v,
            default => $v,
        }, $values);
    }
}

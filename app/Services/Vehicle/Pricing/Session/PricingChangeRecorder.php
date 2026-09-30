<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Session;

use App\Models\Vehicle\Pricing\Addon;
use App\Models\Vehicle\Pricing\AddonHistory;
use App\Models\Vehicle\Pricing\ChangeFlag;
use App\Models\Vehicle\Pricing\DealerCharge;
use App\Models\Vehicle\Pricing\Discount;
use App\Models\Vehicle\Pricing\DiscountHistory;
use App\Models\Vehicle\Pricing\Hold;
use App\Models\Vehicle\Pricing\InsAddonRate;
use App\Models\Vehicle\Pricing\InsBaseRule;
use App\Models\Vehicle\Pricing\InsDefault;
use App\Models\Vehicle\Pricing\InsIdvSlot;
use App\Models\Vehicle\Pricing\PermitMap;
use App\Models\Vehicle\Pricing\Pricing;
use App\Models\Vehicle\Pricing\PricingHistory;
use App\Models\Vehicle\Pricing\Profile;
use App\Models\Vehicle\Pricing\RtoRule;
use App\Models\Vehicle\Pricing\SessionChange;
use App\Models\Vehicle\Pricing\Snapshot;
use App\Models\Vehicle\Pricing\TcsConfig;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\SubSegment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Records every row a pricing session inserts, updates, soft-deletes or expires (DEC-073) into
 * xlr8_vehicle_pricing_session_changes, so Discard can undo exactly that session's work and nothing else.
 *
 *   app(PricingChangeRecorder::class)->within($session->id, fn () => $importer->run(...));
 *
 * Model writes are captured by PricingChangeObserver (registered on MODELS in AppServiceProvider); bulk expiries call
 * captureBulk() before they update. Outside within() nothing is recorded. The log is the SessionChange model (DEC-093).
 */
class PricingChangeRecorder
{
    /** The models whose rows a pricing session records (and Discard undoes). */
    public const MODELS = [
        Pricing::class, PricingHistory::class,
        Addon::class, Discount::class, AddonHistory::class, DiscountHistory::class,
        DealerCharge::class, RtoRule::class,
        InsBaseRule::class, InsIdvSlot::class,
        InsDefault::class, InsAddonRate::class, PermitMap::class,
        TcsConfig::class, Snapshot::class,
        Profile::class, Hold::class,
        ChangeFlag::class, Variant::class,
        VehicleModel::class, Segment::class, SubSegment::class,
    ];

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
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
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
        $changes = SessionChange::query()->ofSession($sessionId)->orderByDesc('id')->get();
        $models = $this->modelsByTable();

        foreach ($changes as $change) {
            $model = $models[$change->table_name] ?? null;
            if ($model === null) {
                continue;
            }
            // the stored row as it was: no events, timestamps, actor stamps or scopes while undoing (DEC-073)
            $row = $model::query()->withoutGlobalScopes()->whereKey($change->row_id)->toBase();
            if ($change->action === 'insert') {
                $row->delete();   // the row did not exist before this session
            } elseif (! empty($change->before)) {
                $row->update($change->before);
            }
        }
        SessionChange::query()->ofSession($sessionId)->delete();

        return $changes->count();
    }

    /** @param  array<string, mixed>|null  $before */
    private function log(string $table, int $rowId, string $action, ?array $before): void
    {
        if ($this->sessionId === null || $table === (new SessionChange)->getTable()) {
            return;
        }
        SessionChange::query()->create([
            'import_session_id' => $this->sessionId,
            'table_name' => $table,
            'row_id' => $rowId,
            'action' => $action,
            'before' => $before === null ? null : $this->plain($before),
            'created_by' => auth(backpack_guard_name())->id() ?? auth()->id(),
        ]);
    }

    /** @return array<string, class-string<Model>> table name => model */
    private function modelsByTable(): array
    {
        $out = [];
        foreach (self::MODELS as $model) {
            $out[(new $model)->getTable()] = $model;
        }

        return $out;
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

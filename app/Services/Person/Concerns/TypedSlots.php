<?php

declare(strict_types=1);

namespace App\Services\Person\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * Person child rows (contacts, addresses, bank accounts) live in fixed type "slots": the type
 * column is a DB enum with a unique key per person (and data type for contacts), and "Primary"
 * is one of those slots (DEC-053).
 *
 * - No type given → Primary when that slot is free, otherwise the first free slot.
 * - Asking for Primary when another row holds it makes this row Primary and demotes the old one
 *   (create: saved in a free slot, then promoted; update: promoted in place).
 * - Every other slot is unique (validation error, never a DB duplicate-key 500).
 * - Deletes are permanent: the unique key covers soft-deleted rows, so a trashed row would block
 *   its slot forever (BUG-175). A trashed row found in a target slot is removed first.
 */
trait TypedSlots
{
    private bool $promoteAfterSave = false;

    /** The type column (contact_type / address_type / account_type). */
    abstract protected function slotField(): string;

    /** Columns that, with the type, form the unique key. @return list<string> */
    abstract protected function slotGroup(): array;

    /** @return list<string> */
    abstract protected function slotTypes(): array;

    /** Make the row Primary, demoting the current Primary (model method). */
    abstract protected function promote(Model $model): void;

    /** Permanently delete a row (frees its slot). */
    public function delete(Model $model): void
    {
        $model->forceDelete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, list<mixed>>
     */
    public function rules(array $data, ?Model $current = null): array
    {
        $rules = parent::rules($data, $current);

        // Primary is never "taken": asking for it promotes this row instead.
        if (($data[$this->slotField()] ?? null) === 'Primary') {
            $rules[$this->slotField()] = array_values(array_filter(
                $rules[$this->slotField()],
                fn ($rule) => ! $rule instanceof Unique,
            ));
        }

        return $rules;
    }

    /** @param  array<string, mixed>  $data */
    protected function beforeCreate(array &$data): void
    {
        $slot = $this->slotField();
        $requested = $data[$slot] ?? null;

        if ($requested === null) {
            $data[$slot] = $this->slotTaken($data, 'Primary') ? $this->freeSlot($data) : 'Primary';
        } elseif ($requested === 'Primary' && $this->slotTaken($data, 'Primary')) {
            $data[$slot] = $this->freeSlot($data);
            $this->promoteAfterSave = true;
        }

        $this->clearTrashedSlot($data, $data[$slot]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function beforeUpdate(Model $model, array &$data): void
    {
        $slot = $this->slotField();

        if (($data[$slot] ?? null) === 'Primary' && $model->{$slot} !== 'Primary') {
            unset($data[$slot]);
            $this->promoteAfterSave = true;
        } elseif (isset($data[$slot]) && $data[$slot] !== $model->{$slot}) {
            $this->clearTrashedSlot(array_merge($model->only($this->slotGroup()), $data), $data[$slot]);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function afterSave(Model $model, array $input, bool $created): void
    {
        $wantsPrimary = $this->promoteAfterSave || filter_var($input['is_primary'] ?? false, FILTER_VALIDATE_BOOL);
        $this->promoteAfterSave = false;

        if ($wantsPrimary && $model->{$this->slotField()} !== 'Primary') {
            $this->promote($model);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function slotTaken(array $data, string $type): bool
    {
        return $this->slotQuery($data)->where($this->slotField(), $type)->exists();
    }

    /** @param  array<string, mixed>  $data */
    private function freeSlot(array $data): string
    {
        $used = $this->slotQuery($data)->pluck($this->slotField())->all();

        foreach ($this->slotTypes() as $type) {
            if ($type !== 'Primary' && ! in_array($type, $used, true)) {
                return $type;
            }
        }

        $this->fail($this->slotField(), 'All '.implode(', ', $this->slotTypes()).' slots are already used for this person.');
    }

    /** @param  array<string, mixed>  $data */
    private function clearTrashedSlot(array $data, string $type): void
    {
        $this->slotQuery($data, true)->onlyTrashed()->where($this->slotField(), $type)->get()->each->forceDelete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Builder<Model>
     */
    private function slotQuery(array $data, bool $withTrashed = false)
    {
        $query = $withTrashed ? $this->model()::withTrashed() : $this->model()::query();
        foreach ($this->slotGroup() as $column) {
            $query->where($column, $data[$column] ?? null);
        }

        return $query;
    }
}

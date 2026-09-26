<?php

declare(strict_types=1);

namespace App\Models\Admin\Concerns;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Promotion to the "Primary" type slot for person child rows whose type column is a DB enum
 * with a unique key per person (DEC-053).
 *
 * The old Primary takes this row's former slot (a swap). MySQL checks unique keys per statement,
 * so this row is first parked in a free slot; soft-deleted rows count as used because the
 * unique key covers them too. Fails only when every slot is used.
 */
trait SwapsPrimarySlot
{
    /**
     * @param  list<string>  $groupColumns  columns that, with the type, form the unique key
     * @param  list<string>  $types  every enum value of the type column
     */
    protected function swapIntoPrimary(string $typeColumn, array $groupColumns, array $types): void
    {
        DB::transaction(function () use ($typeColumn, $groupColumns, $types) {
            $siblings = static::withTrashed()->where('id', '!=', $this->id);
            foreach ($groupColumns as $column) {
                $siblings->where($column, $this->{$column});
            }

            $oldPrimary = (clone $siblings)->whereNull('deleted_at')->where($typeColumn, 'Primary')->first();
            $formerSlot = $this->{$typeColumn};

            if ($oldPrimary && $formerSlot !== 'Primary') {
                $used = (clone $siblings)->pluck($typeColumn)->push($formerSlot)->all();
                $parking = collect($types)->first(fn (string $type) => ! in_array($type, $used, true));
                if ($parking === null) {
                    throw new RuntimeException("No free {$typeColumn} slot to swap the Primary through.");
                }

                $this->{$typeColumn} = $parking;
                $this->save();

                $oldPrimary->{$typeColumn} = $formerSlot;
                $oldPrimary->save();
            }

            $this->{$typeColumn} = 'Primary';
            $this->save();
        });
    }
}

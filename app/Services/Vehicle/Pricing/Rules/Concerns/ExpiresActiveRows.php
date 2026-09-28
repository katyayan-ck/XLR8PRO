<?php

declare(strict_types=1);

namespace App\Services\Vehicle\Pricing\Rules\Concerns;

use App\Services\Vehicle\Pricing\Session\PricingChangeRecorder;
use Illuminate\Support\Facades\Schema;

/**
 * WEF history for pricing rule sets (Machine Spec v3.1.1): importing a new set first expires the
 * live one — `is_active = 0` and, where the table has it, `expired_on = WEF` — and never deletes
 * history. The only bulk write on these tables, owned by the entity service (DEC-056).
 */
trait ExpiresActiveRows
{
    /**
     * Expire every active row — or only one group's (e.g. ['addon_type' => 'RSA']: group imports
     * expire only their group, RSA ≠ Shield); returns how many were expired.
     *
     * @param  array<string, mixed>  $group
     */
    public function expireActive(string $wefDate, array $group = []): int
    {
        $model = new ($this->model());
        $table = $model->getTable();

        $changes = ['is_active' => 0, 'updated_at' => now()];
        if (Schema::hasColumn($table, 'expired_on')) {
            $changes['expired_on'] = $wefDate;
        }
        if (Schema::hasColumn($table, 'updated_by')) {
            $changes['updated_by'] = auth(backpack_guard_name())->id() ?? auth()->id();
        }

        $query = $this->model()::query()->where($group)->where('is_active', 1);
        // a pricing session records what it expires so Discard can restore it (DEC-073)
        app(PricingChangeRecorder::class)->captureBulk($query, array_keys($changes));

        return $query->update($changes);
    }
}

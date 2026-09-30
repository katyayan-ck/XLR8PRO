<?php

namespace App\Models\Vehicle\Pricing;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One row a pricing session wrote (DEC-073): `insert` / `update` / `soft_delete` of `table_name`.`row_id`, with the
 * previous values in `before`, so Discard can undo exactly that session's work. Append-only log — no audit columns
 * beyond `created_at` / `created_by`, no soft deletes. Written only by `PricingChangeRecorder`.
 *
 * @property int $id
 * @property int $import_session_id
 * @property string $table_name
 * @property int $row_id
 * @property string $action
 * @property array<string, mixed>|null $before
 */
class SessionChange extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'xlr8_vehicle_pricing_session_changes';

    protected $fillable = ['import_session_id', 'table_name', 'row_id', 'action', 'before', 'created_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['before' => 'array', 'created_at' => 'datetime'];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOfSession(Builder $query, int $sessionId): Builder
    {
        return $query->where('import_session_id', $sessionId);
    }
}

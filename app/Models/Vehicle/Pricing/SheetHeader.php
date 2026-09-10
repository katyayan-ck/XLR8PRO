<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class SheetHeader extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_vehicle_pricing_sheet_headers';

    protected $fillable = [
        'sheet_code',
        'field_code',
        'label',
        'aliases',
        'data_type',
        'is_required',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'aliases'     => 'array',
        'is_required' => 'boolean',
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
        'deleted_at'  => 'datetime',
    ];

    // ── Scopes ───────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForSheet(Builder $query, string $sheetCode): Builder
    {
        return $query->where('sheet_code', strtoupper(trim($sheetCode)));
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    // ── Helpers ──────────────────────────────────────────────────

    /**
     * All labels that map to this field (primary + aliases).
     */
    public function allLabels(): array
    {
        $labels = [mb_strtolower(trim((string) $this->label))];
        foreach ((array) $this->aliases as $alias) {
            $alias = mb_strtolower(trim((string) $alias));
            if ($alias !== '') {
                $labels[] = $alias;
            }
        }

        return array_values(array_unique($labels));
    }
}

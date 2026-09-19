<?php

/**
 * Path: app/Models/Vehicle/Pricing/ImportSession.php
 *
 * Accepts both `notes` (current jobs/services) and `remarks` (older column).
 */

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class ImportSession extends BaseModel
{
    use SoftDeletes;

    protected $table = 'xlr8_vehicle_pricing_import_sessions';

    public const STAGE_IDLE             = 'idle';
    public const STAGE_DETECTING        = 'detecting';
    public const STAGE_AWAITING_VEHICLE = 'awaiting_vehicle';
    public const STAGE_IMPORTING_PRICES = 'importing_prices';
    public const STAGE_AWAITING_ADDONS  = 'awaiting_addons';
    public const STAGE_IMPORTING_ADDONS = 'importing_addons';
    public const STAGE_AWAITING_RULES   = 'awaiting_rules';
    public const STAGE_CALCULATING      = 'calculating';
    public const STAGE_SUMMARY          = 'summary';
    public const STAGE_COMPLETED        = 'completed';
    public const STAGE_CANCELLED        = 'cancelled';

    public const STATUS_IDLE      = 'idle';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'status',
        'current_stage',
        'selected_sheets',
        'selected_segments',
        'wef_date',
        'hold_scopes',
        'stats',
        'source_filename',
        'notes',
        'remarks',
        'cancelled_at',
        'cancelled_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'selected_sheets'   => 'array',
            'selected_segments' => 'array',
            'hold_scopes'       => 'array',
            'stats'             => 'array',
            'wef_date'          => 'date',
            'cancelled_at'      => 'datetime',
        ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereNotIn('current_stage', [
                self::STAGE_COMPLETED,
                self::STAGE_CANCELLED,
            ]);
    }

    public function isTerminal(): bool
    {
        return in_array($this->current_stage, [
            self::STAGE_COMPLETED,
            self::STAGE_CANCELLED,
        ], true)
            || in_array($this->status, [
                self::STATUS_COMPLETED,
                self::STATUS_CANCELLED,
            ], true);
    }

    public function isActiveProcess(): bool
    {
        return ! $this->isTerminal() && $this->status === self::STATUS_ACTIVE;
    }

    public function setNotesAttribute(?string $value): void
    {
        $this->attributes['notes'] = $value;
        if (Schema::hasColumn($this->getTable(), 'remarks')) {
            $this->attributes['remarks'] = $value;
        }
    }

    public function getNotesAttribute($value): ?string
    {
        if ($value !== null && $value !== '') {
            return $value;
        }

        return $this->attributes['remarks'] ?? null;
    }
}

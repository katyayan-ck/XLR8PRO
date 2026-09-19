<?php

namespace App\Models\Vehicle\Pricing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class Hold extends BaseModel
{
    protected $table = 'xlr8_vehicle_pricing_holds';

    protected $fillable = [
        'scope',
        'is_held',
        'held_at',
        'held_by',
        'hold_reason',
        'reopened_at',
        'reopened_by',
        'reopen_reason',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_held'     => 'boolean',
            'held_at'     => 'datetime',
            'reopened_at' => 'datetime',
        ]);
    }

    public function scopeHeld(Builder $query): Builder
    {
        return $query->where('is_held', true);
    }

    public static function isHeld(string $scope = 'ALL'): bool
    {
        $scope = strtoupper($scope);

        if (self::query()->held()->where('scope', $scope)->exists()) {
            return true;
        }

        return self::query()->held()->where('scope', 'ALL')->exists();
    }

    public static function putOnHold(string $scope, ?string $reason = null, $userId = null): self
    {
        return self::updateOrCreate(
            ['scope' => strtoupper($scope)],
            [
                'is_held'       => true,
                'held_at'       => now(),
                'held_by'       => $userId ?? auth()->id(),
                'hold_reason'   => $reason,
                'reopened_at'   => null,
                'reopened_by'   => null,
                'reopen_reason' => null,
            ]
        );
    }

    public static function reopen(string $scope, ?string $reason = null, $userId = null): ?self
    {
        $hold = self::where('scope', strtoupper($scope))->first();
        if (!$hold) {
            return null;
        }

        $hold->update([
            'is_held'       => false,
            'reopened_at'   => now(),
            'reopened_by'   => $userId ?? auth()->id(),
            'reopen_reason' => $reason,
        ]);

        return $hold;
    }
}
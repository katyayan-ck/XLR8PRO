<?php

namespace App\Models\Admin;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserReporting extends BaseModel
{
    protected $table = 'xlr8_admin_user_reporting';

    protected $fillable = [
        'user_id',
        'topic',
        'reports_to_user_id',
        'scope_type',
        'scope_code',
        'max_levels',
        'extra_data',
        'is_active',
        'from_date',
        'to_date',
        'notes',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'from_date'  => 'date',
        'to_date'    => 'date',
        'extra_data' => 'array',
        'max_levels' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reports_to_user_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('from_date')->orWhere('from_date', '<=', now()->toDateString());
            })
            ->where(function ($q) {
                $q->whereNull('to_date')->orWhere('to_date', '>=', now()->toDateString());
            });
    }

    public function scopeForTopic($query, string $topic)
    {
        return $query->where('topic', $topic);
    }

    public function scopeWithScope($query, ?string $scopeType = null, ?string $scopeCode = null)
    {
        if ($scopeType && $scopeCode) {
            return $query->where('scope_type', $scopeType)
                         ->where('scope_code', $scopeCode);
        }
        return $query->whereNull('scope_type');
    }
}
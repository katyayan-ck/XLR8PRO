<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImportLog extends Model
{
    use SoftDeletes;

    protected $table = 'import_logs';

    protected $fillable = [
        'user_id',
        'filename',
        'import_type',
        'total_records',
        'imported_count',
        'skipped_count',
        'errors_count',
        'errors',
        'warnings',
        'status',
        'duration_seconds',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'errors' => 'array',
        'warnings' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationship with User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Get successful imports
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope: Get failed imports
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Get partial imports
     */
    public function scopePartial($query)
    {
        return $query->where('status', 'partial');
    }

    /**
     * Get import type label
     */
    public function getImportTypeLabel()
    {
        return match ($this->import_type) {
            'standard_users' => 'Standard Users',
            'rules_users' => 'Rules Users',
            'vehicle_definition' => 'Vehicle Definition',
            default => $this->import_type,
        };
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClass()
    {
        return match ($this->status) {
            'success' => 'badge-success',
            'partial' => 'badge-warning',
            'failed' => 'badge-danger',
            default => 'badge-secondary',
        };
    }
}

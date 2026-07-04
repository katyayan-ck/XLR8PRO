<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExportLog extends Model
{
    use SoftDeletes;

    protected $table = 'export_logs';

    protected $fillable = [
        'user_id',
        'filename',
        'export_type',
        'total_records',
        'filters',
        'file_path',
        'file_size',
        'status',
        'duration_seconds',
        'started_at',
        'completed_at',
        'downloaded_at',
        'download_count',
    ];

    protected $casts = [
        'filters' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'downloaded_at' => 'datetime',
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
     * Scope: Get successful exports
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope: Get failed exports
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Get downloaded exports
     */
    public function scopeDownloaded($query)
    {
        return $query->whereNotNull('downloaded_at');
    }

    /**
     * Get export type label
     */
    public function getExportTypeLabel()
    {
        return match ($this->export_type) {
            'standard_users' => 'Standard Users',
            'rules_users' => 'Rules Users',
            'vehicle_inventory' => 'Vehicle Inventory',
            default => $this->export_type,
        };
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClass()
    {
        return match ($this->status) {
            'success' => 'badge-success',
            'failed' => 'badge-danger',
            default => 'badge-secondary',
        };
    }

    /**
     * Mark as downloaded
     */
    public function markAsDownloaded()
    {
        $this->update([
            'downloaded_at' => now(),
            'download_count' => $this->download_count + 1,
        ]);
    }

    /**
     * Format file size for display
     */
    public function getFormattedFileSize()
    {
        if (!$this->file_size) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;

        foreach ($units as $unit) {
            if ($size < 1024) {
                return round($size, 2) . ' ' . $unit;
            }
            $size /= 1024;
        }

        return round($size, 2) . ' TB';
    }
}

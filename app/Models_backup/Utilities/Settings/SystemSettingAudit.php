<?php

namespace App\Models\Utilities\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SystemSettingAudit Model
 * 
 * Tracks all changes to system settings for compliance and debugging
 */
class SystemSettingAudit extends Model
{
    protected $table = 'xlr8_utils_system_setting_audit';

    protected $fillable = [
        'setting_id',
        'user_id',
        'action',
        'old_value',
        'new_value',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship: Setting
     */
    public function setting(): BelongsTo
    {
        return $this->belongsTo(SystemSetting::class, 'setting_id');
    }

    /**
     * Relationship: User who made the change
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get old value as array/object
     */
    public function getOldValueDecoded(): array
    {
        return json_decode($this->old_value, true) ?? [];
    }

    /**
     * Get new value as array/object
     */
    public function getNewValueDecoded(): array
    {
        return json_decode($this->new_value, true) ?? [];
    }
}

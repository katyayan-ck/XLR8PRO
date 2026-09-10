<?php

namespace App\Models\IAM;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OtpToken Model
 *
 * Represents OTP tokens for user authentication
 * - Stores hashed OTP codes with expiration times
 * - Tracks when OTP was used to prevent reuse
 * - Soft deletes for audit trail
 *
 * @property int $id
 * @property int $user_id Foreign key to users table
 * @property string $mobile 10-digit mobile number
 * @property string $otp_hash Bcrypt hashed OTP
 * @property \Carbon\Carbon $expires_at ISO 8601 timestamp
 * @property \Carbon\Carbon|null $used_at ISO 8601 timestamp when OTP was consumed
 * @property int|null $created_by User who created the record
 * @property int|null $updated_by User who updated the record
 * @property int|null $deleted_by User who deleted the record
 * @property \Carbon\Carbon $created_at ISO 8601 creation timestamp
 * @property \Carbon\Carbon $updated_at ISO 8601 update timestamp
 * @property \Carbon\Carbon|null $deleted_at ISO 8601 soft delete timestamp
 *
 * @method static where(...$args) Query builder
 * @method static create(array $data) Create new record
 */
class OtpToken extends BaseModel
{
    /**
     * Table name
     *
     * @var string
     */
    protected $table = 'xlr8_iam_otp_token';

    /**
     * Mass assignable attributes
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'mobile',
        'otp_hash',
        'expires_at',
        'used_at',
        'created_by',
        'updated_by',
    ];

    /**
     * Attribute type casting
     *
     * All timestamps are automatically cast to Carbon instances
     * Carbon automatically formats them as ISO 8601 in JSON responses
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime', // ISO 8601 format
        'used_at' => 'datetime',     // ISO 8601 format
        'created_at' => 'datetime',  // ISO 8601 format
        'updated_at' => 'datetime',  // ISO 8601 format
        'deleted_at' => 'datetime',  // ISO 8601 format
    ];

    /**
     * Hidden attributes (not shown in JSON responses)
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'otp_hash', // Never expose hashed OTP
    ];

    /**
     * Enable soft deletes for audit trail
     */
    use \Illuminate\Database\Eloquent\SoftDeletes;

    // ╔════════════════════════════════════════════════════════╗
    // ║ RELATIONSHIPS ║
    // ╚════════════════════════════════════════════════════════╝

    /**
     * Relationship: User who owns this OTP token
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: User who created this record
     *
     * @return BelongsTo
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: User who updated this record
     *
     * @return BelongsTo
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ╔════════════════════════════════════════════════════════╗
    // ║ SCOPES ║
    // ╚════════════════════════════════════════════════════════╝

    /**
     * Scope: Get only unexpired OTPs
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope: Get only unused OTPs
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNotUsed($query)
    {
        return $query->whereNull('used_at');
    }

    /**
     * Scope: Get OTP for specific mobile
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $mobile 10-digit mobile number
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForMobile($query, string $mobile)
    {
        return $query->where('mobile', $mobile);
    }

    /**
     * Scope: Get OTP for specific user
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId User ID
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ╔════════════════════════════════════════════════════════╗
    // ║ HELPER METHODS ║
    // ╚════════════════════════════════════════════════════════╝

    /**
     * Check if OTP is expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return now() > $this->expires_at;
    }

    /**
     * Check if OTP is used
     *
     * @return bool
     */
    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /**
     * Mark OTP as used
     *
     * @return bool
     */
    public function markAsUsed(): bool
    {
        return $this->update([
            'used_at' => now(),
            'updated_by' => auth()->id() ?? $this->user_id,
        ]);
    }

    /**
     * Get human-readable expiration time remaining
     *
     * @return string|null
     */
    public function getExpirationRemaining(): ?string
    {
        if ($this->isExpired()) {
            return 'Expired';
        }

        $minutes = now()->diffInMinutes($this->expires_at);
        return $minutes . ' minute' . ($minutes !== 1 ? 's' : '');
    }
}

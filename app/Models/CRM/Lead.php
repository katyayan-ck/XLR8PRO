<?php

namespace App\Models\CRM;

use App\Models\BaseModel;
use App\Models\User;
use App\Models\Traits\HasCommunications;
use App\Models\Traits\HasColumnTransformations;
use Illuminate\Support\Facades\Cache;
use App\Models\Vehicle\VehicleModel;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\Color;

class Lead extends BaseModel
{
    // use HasCommunications, HasColumnTransformations;

    protected $table = 'xlr8_crm_leads';

    protected $fillable = [
        'lead_no',
        'capture_date',
        'source_code',
        'referral_details',
        'first_name',
        'last_name',
        'mobile',
        'email',
        'occupation',
        'segment_code',
        'model_code',
        'variant_code',
        'color_code',
        'expected_delivery_date',
        'notes',
        'status',
        'priority',
        'verified_by',
        'verified_at',
        'conversion_notes',
        'assigned_to',
        'assigned_at',
    ];

    protected $casts = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->casts = array_merge($this->casts, [
            'capture_date' => 'date',
            'expected_delivery_date' => 'date',
            'verified_at' => 'datetime',
            'assigned_at' => 'datetime',
        ]);
    }

    protected array $columnTransformations = [
        'first_name' => 'trim|ucwords',
        'last_name' => 'trim|ucwords',
        'mobile' => 'trim',
        'email' => 'trim|lowercase',
        'model_code' => 'uppercase|trim',
        'variant_code' => 'uppercase|trim',
        'color_code' => 'uppercase|trim',
        'source_code' => 'uppercase|trim',
    ];

    public const STATUS_NEW = 'new';
    public const STATUS_IN_FOLLOWUP = 'in_followup';
    public const STATUS_QUOTATION_SENT = 'quotation_sent';
    public const STATUS_BOOKING_DONE = 'booking_done';
    public const STATUS_OTF_GENERATED = 'otf_generated';
    public const STATUS_LOST = 'lost';
    public const STATUS_CANCELLED = 'cancelled';

    // ==================== RELATIONSHIPS (Code-based) ====================
    public function source()
    {
        return $this->belongsTo(LeadSource::class, 'source_code', 'code');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function enquiry()
    {
        return $this->hasOne(Enquiry::class, 'lead_id');
    }

    // ==================== SCOPES ====================
    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', [self::STATUS_LOST, self::STATUS_CANCELLED, self::STATUS_BOOKING_DONE]);
    }

    public function scopeForFsc($query, int $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // ==================== ACCESSORS ====================
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . ($this->last_name ?? ''));
    }

    // public function getStatusLabelAttribute(): string
    // {
    //     return app(\App\Services\KeywordValueService::class)->getEnum('LEAD_STATUS', $this->status)
    //         ?? ucfirst(str_replace('_', ' ', $this->status));
    // }

    // ==================== CACHING ====================
    public static function getOpenCountByConsultant(int $userId): int
    {
        return Cache::remember("crm_lead_open_count_{$userId}", 300, function () use ($userId) {
            return self::open()->forFsc($userId)->count();
        });
    }

    public static function clearConsultantCache(int $userId): void
    {
        Cache::forget("crm_lead_open_count_{$userId}");
    }

    public function model()
    {
        return $this->belongsTo(
            VehicleModel::class,
            'model_code',
            'code'
        );
    }

    public function segment()
    {
        return $this->belongsTo(
            Segment::class,
            'segment_code',
            'code'
        );
    }

    public function variant()
    {
        return $this->belongsTo(
            Variant::class,
            'variant_code',
            'code'
        );
    }

    public function color()
    {
        return $this->belongsTo(
            Color::class,
            'color_code',
            'code'
        );
    }

    public function getModelNameAttribute()
    {
        return $this->model?->name;
    }

    public function getVariantNameAttribute()
    {
        return $this->variant?->display_name
            ?? $this->variant?->custom_name
            ?? $this->variant?->oem_name;
    }

    public function getColorNameAttribute()
    {
        return $this->color?->name;
    }
}
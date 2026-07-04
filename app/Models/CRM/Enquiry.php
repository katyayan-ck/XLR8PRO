<?php

namespace App\Models\CRM;

use App\Models\Admin\Person;
use App\Models\BaseModel;
use App\Models\User;
use App\Models\Vehicle\Color;
use App\Models\Vehicle\Segment;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Traits\HasCommunications;
use App\Traits\HasColumnTransformations;
use Illuminate\Support\Facades\Cache;

class Enquiry extends BaseModel
{
    // use HasCommunications, HasColumnTransformations;

    protected $table = 'xlr8_crm_enquiries';

    protected $fillable = [
        'enquiry_no',
        'enquiry_date',
        'lead_no',
        'person_code',
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
        'place_of_registration',
        'registration_by',
        'insurance_by',
        'has_rsa',
        'has_extended_warranty',
        'expected_delivery_date',
        'dms_enquiry_no',
        'sales_consultant_id',
        'status',
        'lost_reason',
        'priority',
        'notes',
        'conversion_notes',
    ];

    protected $casts = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->casts = array_merge($this->casts, [
            'enquiry_date' => 'date',
            'expected_delivery_date' => 'date',
            'has_rsa' => 'boolean',
            'has_extended_warranty' => 'boolean',
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
        'lead_no' => 'uppercase|trim',
    ];

    public const STATUS_NEW = 'new';
    public const STATUS_IN_FOLLOWUP = 'in_followup';
    public const STATUS_QUOTATION_SENT = 'quotation_sent';
    public const STATUS_QUOTATION_APPROVED = 'quotation_approved';
    public const STATUS_BOOKING_DONE = 'booking_done';
    public const STATUS_OTF_GENERATED = 'otf_generated';
    public const STATUS_LOST = 'lost';
    public const STATUS_CANCELLED = 'cancelled';

    // ==================== CODE-BASED RELATIONSHIPS ====================
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_no', 'lead_no');
    }

    public function source()
    {
        return $this->belongsTo(LeadSource::class, 'source_code', 'code');
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_code', 'person_code');
    }

    public function salesConsultant()
    {
        return $this->belongsTo(User::class, 'sales_consultant_id');
    }

    public function vehicleModel()
    {
        return $this->belongsTo(VehicleModel::class, 'model_code', 'model_code');
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
        return $this->belongsTo(Variant::class, 'variant_code', 'code');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_code', 'code');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class, 'enquiry_no', 'enquiry_no');
    }

    // Scopes, accessors, and caching remain the same as previous version
    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', [self::STATUS_LOST, self::STATUS_CANCELLED, self::STATUS_BOOKING_DONE]);
    }

    public function scopeForConsultant($query, int $userId)
    {
        return $query->where('sales_consultant_id', $userId);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . ($this->last_name ?? ''));
    }

    // public function getStatusLabelAttribute(): string
    // {
    //     return app(\App\Services\KeywordValueService::class)->getEnum('ENQUIRY_STATUS', $this->status)
    //         ?? ucfirst(str_replace('_', ' ', $this->status));
    // }

    public static function getOpenCountByConsultant(int $userId): int
    {
        return Cache::remember("crm_enquiry_open_count_{$userId}", 300, function () use ($userId) {
            return self::open()->forConsultant($userId)->count();
        });
    }

    public static function clearConsultantCache(int $userId): void
    {
        Cache::forget("crm_enquiry_open_count_{$userId}");
    }
}
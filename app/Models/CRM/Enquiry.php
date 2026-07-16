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
        'enquiry_type',
        'source_code',
        'sub_source',

        'person_code',

        'reference_details',
        'referred_by',
        'referee_phone',
        'referee_name',

        'planned_campaign',

        'likely_purchase_date',

        'activity_type',
        'activity_segment',
        'activity_model',
        'activity_start_date',
        'activity_end_date',
        'activity_branch',
        'activity_location',

        'first_name',
        'last_name',
        'mobile',
        'email',

        'occupation_type',
        'occupation_sub_type',

        'customer_type',

        'company_name',

        'gender',

        'dob',

        'marital_status',
        'marriage_date',

        'age_group',

        'zipcode',
        'tehsil',
        'district',
        'city',

        'has_ev',

        'purchase_type',

        'exchange_make',
        'exchange_model',
        'vehicle_no',

        'remarks',

        'segment_code',
        'model_code',
        'variant_code',
        'color_code',

        'fuel_type',
        'transmission',
        'drivetrain',
        'seating',

        'usage_area',
        'km_travelled_daily',

        'application_type',
        'application',

        'place_of_registration',

        'dealer_branch',
        'dealer_location',

        'sales_consultant_id',

        'followup_type',
        'followup_date',
        'followup_time',

        'real_status',

        'created_by',
        'updated_by',
        'deleted_by',

    ];

    protected $casts = [

        // 'likely_purchase_date' => 'date',

        'activity_start_date' => 'date',

        'activity_end_date' => 'date',

        'dob' => 'date',

        'marriage_date' => 'date',

        'followup_date' => 'date',

    ];

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

        'enquiry_no' => 'uppercase|trim',

        'first_name' => 'trim|ucwords',

        'last_name' => 'trim|ucwords',

        'mobile' => 'trim',

        'email' => 'trim|lowercase',

        'reference_details' => 'trim',

        'referred_by' => 'trim',

        'referee_phone' => 'trim',

        'referee_name' => 'trim|ucwords',

        'company_name' => 'trim',

        'exchange_make' => 'trim',

        'exchange_model' => 'trim',

        'vehicle_no' => 'uppercase|trim',

        'remarks' => 'trim',

        'source_code' => 'uppercase|trim',

        'sub_source' => 'uppercase|trim',

        'segment_code' => 'uppercase|trim',

        'model_code' => 'uppercase|trim',

        'variant_code' => 'uppercase|trim',

        'color_code' => 'uppercase|trim',

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


    

    // public function campaign()
    // {
    //     return $this->belongsTo(
    //         Campaign::class,
    //         'planned_campaign',
    //         'name'
    //     );
    // }

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

    // ==================== CURRENT-ORIGIN / REAL-STATUS BASE SCOPES ====================

    // real_status = 1 => live/active record (this is the "status 1" flag)
    // public function scopeActive($query)
    // {
    //     return $query->where('real_status', 1);
    // }

    public function scopeCurrentOrigin($query, string $origin)
    {
        return $query->where('current_origin', strtoupper($origin));
    }

    public function scopeQuick($query)
    {
        return $query->currentOrigin('QUICK')->active();
    }

    public function scopeLong($query)
    {
        return $query->currentOrigin('LONG')->active();
    }

    public function scopeReference($query)
    {
        return $query->currentOrigin('REFERENCE')->active();
    }

    public function scopeVirtual($query)
    {
        return $query->currentOrigin('VIRTUAL')->active();
    }

    public function scopeWhatsapp($query)
    {
        return $query->currentOrigin('WHATSAPP')->active();
    }

    // ==================== ASSIGNED / UNASSIGNED SCOPES ====================
    // Assigned  = sales_consultant_id OR sc_mile_id has a value
    // Unassigned = both sales_consultant_id AND sc_mile_id are blank

    public function scopeAssigned($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($q2) {
                $q2->whereNotNull('sales_consultant_id')->where('sales_consultant_id', '!=', '');
            })->orWhere(function ($q2) {
                $q2->whereNotNull('sc_mile_id')->where('sc_mile_id', '!=', '');
            });
        });
    }

    public function scopeUnassigned($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($q2) {
                $q2->whereNull('sales_consultant_id')->orWhere('sales_consultant_id', '');
            })->where(function ($q2) {
                $q2->whereNull('sc_mile_id')->orWhere('sc_mile_id', '');
            });
        });
    }

    // ==================== PER-BLADE COMBINED SCOPES ====================
    // These map 1:1 to the dropdown pages so controllers just call these.

    public function scopeAssignedQuick($query)
    {
        return $query->quick()->assigned();
    }

    public function scopeUnassignedQuick($query)
    {
        return $query->quick()->unassigned();
    }

    public function scopeAssignedLong($query)
    {
        return $query->long()->assigned();
    }

    public function scopeUnassignedLong($query)
    {
        return $query->long()->unassigned();
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
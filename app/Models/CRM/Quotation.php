<?php

namespace App\Models\CRM;

use App\Models\Admin\Person;
use App\Models\BaseModel;
use App\Models\User;
use App\Models\Vehicle\Color;
use App\Models\Vehicle\Variant;
use App\Models\Vehicle\VehicleModel;
use App\Models\Traits\HasColumnTransformations;

class Quotation extends BaseModel
{
    use HasColumnTransformations;

    protected $table = 'xlr8_crm_quotations';

    protected $fillable = [
        'quotation_no',
        'enquiry_no',
        'person_code',
        'segment_code',
        'model_code',
        'variant_code',
        'color_code',
        'sc_code',
        'assigned_to',
        'revision',
        'standard_data',
        'requested_data',
        'proposed_data',
        'final_data',
        'onroad_price',
        'invoice_price',
        'status',
        'fsc_last_remark',
        'approver_last_remark',
    ];

    protected $casts = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->casts = array_merge($this->casts, [
            'standard_data'  => 'array',
            'requested_data' => 'array',
            'proposed_data'  => 'array',
            'onroad_price'   => 'decimal:2',
            'invoice_price'  => 'decimal:2',
            'revision'       => 'integer',
        ]);
    }

    protected array $columnTransformations = [
        'model_code'   => 'uppercase|trim',
        'variant_code' => 'uppercase|trim',
        'color_code'   => 'uppercase|trim',
        'enquiry_no'   => 'uppercase|trim',
    ];

    public const STATUS_RAISED           = 'raised';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED         = 'approved';
    public const STATUS_REJECTED         = 'rejected';
    public const STATUS_REVISED          = 'revised';
    public const STATUS_CLOSED           = 'closed';

    // ==================== CODE-BASED RELATIONSHIPS ====================
    public function enquiry()
    {
        return $this->belongsTo(Enquiry::class, 'enquiry_no', 'enquiry_no');
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_code', 'person_code');
    }

    public function salesConsultant()
    {
        return $this->belongsTo(User::class, 'sc_code');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function vehicleModel()
    {
        return $this->belongsTo(VehicleModel::class, 'model_code', 'code');
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class, 'variant_code', 'code');
    }

    public function color()
    {
        return $this->belongsTo(Color::class, 'color_code', 'code');
    }

    public function actions()
    {
        return $this->hasMany(QuoteAction::class, 'quotation_no', 'quotation_no');
    }

    public function getStatusLabelAttribute(): string
    {
        return app(\App\Services\KeywordValueService::class)->getEnum('QUOTE_STATUS', $this->status)
            ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function getLatestActionAttribute()
    {
        return $this->actions()->first();
    }

    public function registerMediaCollections(): void
    {
        parent::registerMediaCollections();

        $this->addMediaCollection('quotation_pdf')
            ->acceptsMimeTypes(['application/pdf'])
            ->singleFile()
            ->useDisk('public');
    }
}

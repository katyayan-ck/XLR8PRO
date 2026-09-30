<?php

namespace App\Models\CRM;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One follow-up of an enquiry (`xlr8_crm_enquiries_fup`, ~91k rows, linked by `enquiry_no`). Written by the enquiry
 * import (`ImportEnquiriesJob`) and the enquiry screens; read by the dashboard (DEC-093). Not data-scoped itself — scope
 * through its enquiry (`DataScope::apply($q, Enquiry::class, 'e')`).
 *
 * @property int $id
 * @property string $enquiry_no
 * @property ?string $followup_status
 * @property ?string $planned_followup_date
 * @property ?string $actual_followup_date
 */
class EnquiryFollowup extends BaseModel
{
    protected $table = 'xlr8_crm_enquiries_fup';

    protected $fillable = [
        'enquiry_no', 'sc_code', 'sc_mile_id', 'followup_type', 'remark_type', 'planned_followup_date', 'actual_followup_date',
        'followup_status', 'call_duration', 'remarks', 'comments', 'enquiry_date', 'enquiry_type', 'enquiry_source',
        'enquiry_sub_source', 'enquiry_status', 'purchase_type', 'deviation_stage', 'customer_name', 'customer_phone',
        'dealer_location', 'is_active',
    ];

    /** @return BelongsTo<Enquiry, $this> */
    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class, 'enquiry_no', 'enquiry_no');
    }
}

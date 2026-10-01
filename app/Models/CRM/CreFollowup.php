<?php

namespace App\Models\CRM;

use App\Models\BaseModel;

/**
 * A customer-relations (CRE) follow-up of an enquiry (`xlr8_cre_enquiry_fup`), keyed by the Xceler8 enquiry reference
 * (`x8_enq_no`: the enquiry id or `XENQ-{id}`). Written by the enquiry screen's CRE follow-up form (DEC-093).
 *
 * @property int $id
 * @property ?string $x8_enq_no
 * @property ?int $cre_fup_count
 * @property ?string $cre_fup_deviation_stage
 */
class CreFollowup extends BaseModel
{
    protected $table = 'xlr8_cre_enquiry_fup';

    protected $fillable = [
        'enquiry_no', 'quick_enquiry_no', 'x8_enq_no', 'cre_fup_count', 'cre_planned_fup_date', 'cre_actual_fup_date',
        'cre_fup_call_duration', 'cre_fup_deviation_stage', 'cre_enq_stage', 'cre_customer_stage', 'cre_fup_remarks',
        'cre_next_fup_date',
    ];
}

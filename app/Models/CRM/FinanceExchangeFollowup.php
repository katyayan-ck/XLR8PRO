<?php

namespace App\Models\CRM;

use App\Models\BaseModel;

/**
 * A finance (`remark_type` 1) or exchange (`remark_type` 2) follow-up remark on an enquiry (`xlr8_finexch_fup`, linked by
 * `enq_no`). Written by the enquiry finance / exchange edit screens (DEC-093).
 *
 * @property int $id
 * @property string $enq_no
 * @property int $remark_type
 * @property ?int $fup_count
 * @property ?string $remarks
 */
class FinanceExchangeFollowup extends BaseModel
{
    public const FINANCE = 1;

    public const EXCHANGE = 2;

    protected $table = 'xlr8_finexch_fup';

    protected $fillable = ['enq_no', 'remark_type', 'fup_count', 'remarks'];
}

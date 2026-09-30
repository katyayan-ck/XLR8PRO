<?php

namespace App\Models\Module\Finance;

use App\Models\BaseModel;

/**
 * One line of a financier's statement (`xlr8_financer_statement`): a debit / credit against a delivery order number
 * (`do_no`), imported from the financier's statement file (Imports → Sales). Read by the booking OTF / trade-advance
 * screens to match a booking's finance DO (DEC-093).
 *
 * @property int $id
 * @property string $financier_code
 * @property ?string $trans_date
 * @property ?string $trans_description
 * @property ?string $trans_type
 * @property ?string $do_no
 * @property ?string $debit_amount
 * @property ?string $credit_amount
 * @property ?int $running_balance
 * @property ?int $status
 */
class FinancerStatement extends BaseModel
{
    protected $table = 'xlr8_financer_statement';

    protected $fillable = [
        'financier_code', 'trans_date', 'trans_description', 'trans_type', 'do_no', 'debit_amount', 'credit_amount',
        'running_balance', 'status',
    ];
}

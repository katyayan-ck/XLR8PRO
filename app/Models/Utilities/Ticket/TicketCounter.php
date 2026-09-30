<?php

namespace App\Models\Utilities\Ticket;

use Illuminate\Database\Eloquent\Model;

/**
 * The last ticket sequence per branch and financial year (`xlr8_utils_ticket_counter`). Advanced only by
 * `TicketService` under `lockForUpdate()` (DEC-093).
 *
 * @property int $id
 * @property string $branch_code
 * @property string $fy
 * @property int $last_seq
 */
class TicketCounter extends Model
{
    protected $table = 'xlr8_utils_ticket_counter';

    protected $fillable = ['branch_code', 'fy', 'last_seq'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_seq' => 'integer'];
    }
}

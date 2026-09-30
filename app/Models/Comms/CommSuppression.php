<?php

namespace App\Models\Comms;

use Illuminate\Database\Eloquent\Model;

/**
 * An address that must not be contacted on a channel (`xlr8_comm_suppression`: bounce, complaint, opt-out …). Written
 * only by `ContactService::suppress()` (DEC-093).
 *
 * @property int $id
 * @property string $channel
 * @property string $address
 * @property string $reason
 * @property ?string $note
 */
class CommSuppression extends Model
{
    protected $table = 'xlr8_comm_suppression';

    protected $fillable = ['channel', 'address', 'reason', 'note'];
}

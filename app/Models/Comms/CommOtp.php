<?php

namespace App\Models\Comms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A one-time code (`xlr8_comm_otp`): only its hash is stored, the destination masked. Written only by `SmsService::otp()`
 * / `verify()` (DEC-093). Never log the code or the full number.
 *
 * @property int $id
 * @property string $person_code
 * @property string $purpose
 * @property ?string $destination_masked
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property int $attempts
 * @property ?Carbon $used_at
 * @property ?int $outbox_id
 */
class CommOtp extends Model
{
    protected $table = 'xlr8_comm_otp';

    protected $fillable = ['person_code', 'purpose', 'destination_masked', 'code_hash', 'expires_at', 'attempts', 'used_at', 'outbox_id'];

    protected $hidden = ['code_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime', 'attempts' => 'integer'];
    }
}

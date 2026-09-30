<?php

namespace App\Models\Comms;

use Illuminate\Database\Eloquent\Model;

/**
 * A message the sandbox drivers "sent" instead of calling a vendor (`xlr8_comm_sandbox`, append-only; no `updated_at`).
 * Written only by `Comms\Drivers\SandboxDriver` / `SandboxTelephonyDriver` (DEC-093).
 *
 * @property int $id
 * @property ?int $outbox_id
 * @property string $channel
 * @property string $driver
 * @property ?string $to_address
 * @property ?array<string, mixed> $payload
 */
class CommSandbox extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'xlr8_comm_sandbox';

    protected $fillable = ['outbox_id', 'channel', 'driver', 'to_address', 'payload'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}

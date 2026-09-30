<?php

namespace App\Models\Comms;

use Illuminate\Database\Eloquent\Model;

/**
 * A person's consent per channel (`xlr8_comm_consent`): granted / withdrawn, with its source. Written only by
 * `ContactService::setConsent()` (DEC-093).
 *
 * @property int $id
 * @property string $person_code
 * @property string $channel
 * @property bool $granted
 * @property ?string $source
 * @property ?int $changed_by
 */
class CommConsent extends Model
{
    protected $table = 'xlr8_comm_consent';

    protected $fillable = ['person_code', 'channel', 'granted', 'source', 'changed_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['granted' => 'boolean'];
    }
}

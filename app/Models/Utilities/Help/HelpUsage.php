<?php

namespace App\Models\Utilities\Help;

use Illuminate\Database\Eloquent\Model;

/**
 * One help-usage event (W16f, DEC-094): `event` is one of `HelpUsageService::EVENTS`, `ref` the article key, route,
 * masked search text or ticket number. Append-only; written and read only by `HelpUsageService`.
 *
 * @property int $id
 * @property ?int $user_id
 * @property string $event
 * @property ?string $ref
 */
class HelpUsage extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'xlr8_utils_help_usage';

    protected $fillable = ['user_id', 'event', 'ref'];
}

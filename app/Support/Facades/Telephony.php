<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Services\Platform\Comms\TelephonyService;
use Illuminate\Support\Facades\Facade;

/**
 * Telephony (FRS §16): Telephony::dial(), recording(), calls(), dispose().
 *
 * @see TelephonyService
 */
final class Telephony extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TelephonyService::class;
    }
}

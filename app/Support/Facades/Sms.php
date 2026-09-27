<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Services\Platform\Comms\SmsService;
use Illuminate\Support\Facades\Facade;

/**
 * SMS (FRS §14): Sms::send(), otp(), verify().
 *
 * @see SmsService
 */
final class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SmsService::class;
    }
}

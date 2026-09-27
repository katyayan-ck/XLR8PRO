<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Services\Platform\Comms\EmailService;
use Illuminate\Support\Facades\Facade;

/**
 * Email (FRS §13): Email::send([...]).
 *
 * @see EmailService
 */
final class Email extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return EmailService::class;
    }
}

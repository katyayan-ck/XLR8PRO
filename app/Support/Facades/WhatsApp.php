<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Services\Platform\Comms\WhatsAppService;
use Illuminate\Support\Facades\Facade;

/**
 * WhatsApp (FRS §15): WhatsApp::send(), thread(), history().
 *
 * @see WhatsAppService
 */
final class WhatsApp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WhatsAppService::class;
    }
}

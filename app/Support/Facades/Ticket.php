<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Services\Platform\Ticket\TicketService;
use Illuminate\Support\Facades\Facade;

/**
 * Tickets (FRS §6): Ticket::open(), transition(), inbox().
 *
 * @see TicketService
 */
final class Ticket extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TicketService::class;
    }
}

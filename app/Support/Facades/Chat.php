<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Services\Platform\Chat\ChatService;
use Illuminate\Support\Facades\Facade;

/**
 * Chat / history (FRS §3): Chat::event(), remark(), timeline().
 *
 * @see ChatService
 */
final class Chat extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ChatService::class;
    }
}

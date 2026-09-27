<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Services\Platform\Notify\NotifyService;
use Illuminate\Support\Facades\Facade;

/**
 * Notifications (FRS §2): Notify::to($id)->kind('N')->about('QUOTE', $id)->title(...)->send().
 *
 * @see NotifyService
 */
final class Notify extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NotifyService::class;
    }
}

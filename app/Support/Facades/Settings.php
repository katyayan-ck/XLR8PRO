<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Services\Platform\Settings\SettingsService;
use Illuminate\Support\Facades\Facade;

/**
 * Site settings (FRS §1): Settings::get(), getFor(), flag(), set().
 *
 * @see SettingsService
 */
final class Settings extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SettingsService::class;
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Services\Platform\Docs\DocsService;
use Illuminate\Support\Facades\Facade;

/**
 * Documents (FRS §4): Docs::attach(), card(), entitle(), canView(), library().
 *
 * @see DocsService
 */
final class Docs extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DocsService::class;
    }
}

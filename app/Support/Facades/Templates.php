<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Services\Platform\Templates\TemplateService;
use Illuminate\Support\Facades\Facade;

/**
 * Message templates (FRS §12): Templates::render(), saveDraft(), activate().
 *
 * @see TemplateService
 */
final class Templates extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TemplateService::class;
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Services\Platform\Approval\RuleService;
use Illuminate\Support\Facades\Facade;

/**
 * Approval rules (FRS §8.3): Rules::match().
 *
 * @see RuleService
 */
final class Rules extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RuleService::class;
    }
}

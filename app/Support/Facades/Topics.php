<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Services\Platform\Approval\TopicService;
use Illuminate\Support\Facades\Facade;

/**
 * Approval topics (FRS §8): Topics::resolve().
 *
 * @see TopicService
 */
final class Topics extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TopicService::class;
    }
}

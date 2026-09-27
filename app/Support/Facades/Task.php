<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Services\Platform\Task\TaskService;
use Illuminate\Support\Facades\Facade;

/**
 * Tasks (FRS §5): Task::create(), followUp(), inbox(), get().
 *
 * @see TaskService
 */
final class Task extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TaskService::class;
    }
}

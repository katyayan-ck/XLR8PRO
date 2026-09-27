<?php

declare(strict_types=1);

namespace App\Support\Facades;

use App\Services\Platform\Approval\ApprovalService;
use Illuminate\Support\Facades\Facade;

/**
 * Approvals (FRS §7): Approval::open(), counter(), effective(), close().
 *
 * @see ApprovalService
 */
final class Approval extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ApprovalService::class;
    }
}

<?php

namespace App\Exceptions;

use App\Enums\ErrorCodeEnum;

class AccountLockedException extends ApplicationException
{
    private int $lockDurationMinutes = 30;

    public function __construct(
        string $message = 'Account locked due to multiple failed attempts',
        int $lockDurationMinutes = 30
    ) {
        $this->lockDurationMinutes = $lockDurationMinutes;
        parent::__construct($message, ErrorCodeEnum::AUTH_FORBIDDEN, 403);
    }

    public function getLockDurationMinutes(): int
    {
        return $this->lockDurationMinutes;
    }
}

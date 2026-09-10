<?php

namespace App\Exceptions;

use App\Enums\ErrorCodeEnum;

class RateLimitException extends ApplicationException
{
    private int $retryAfterSeconds;
    private string $resource;

    public function __construct(
        string $resource = 'Request',
        int $limit = 3,
        int $windowSeconds = 900
    ) {
        $this->resource = $resource;
        $this->retryAfterSeconds = $windowSeconds;
        $windowMinutes = ceil($windowSeconds / 60);
        $message = "Too many {$resource}. Maximum {$limit} allowed per {$windowMinutes} minutes.";
        parent::__construct($message, ErrorCodeEnum::AUTH_OTP_RATE_LIMIT, 429);
    }

    public function getRetryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }

    public function getResource(): string
    {
        return $this->resource;
    }
}

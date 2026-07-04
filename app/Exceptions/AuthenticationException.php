<?php

namespace App\Exceptions;

use App\Enums\ErrorCodeEnum;

class AuthenticationException extends ApplicationException
{
    public function __construct(
        ErrorCodeEnum|string $errorCode = ErrorCodeEnum::AUTH_UNAUTHORIZED,
        ?string $message = null,
        ?int $statusCode = null
    ) {
        if (is_string($errorCode)) {
            $message = $errorCode;
            $errorCode = ErrorCodeEnum::AUTH_UNAUTHORIZED;
        }

        $message = $message ?? $errorCode->message();
        $statusCode = $statusCode ?? $errorCode->statusCode();

        parent::__construct($message, $errorCode, $statusCode);
    }
}

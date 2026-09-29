<?php

namespace App\Exceptions;

use App\Enums\ErrorCodeEnum;
use Throwable;

/**
 * Concrete throwable domain exception.
 * Use this for business-rule violations in Services.
 * Defaults to VALIDATION_CONSTRAINT_VIOLATION (422); the case it named before did not exist (BUG-208).
 */
class DomainException extends ApplicationException
{
    public function __construct(
        string $message,
        ErrorCodeEnum $errorCode = ErrorCodeEnum::VALIDATION_CONSTRAINT_VIOLATION,
        ?int $statusCode = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $statusCode, $previous);
    }
}

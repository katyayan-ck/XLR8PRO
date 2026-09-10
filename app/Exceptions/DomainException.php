<?php

namespace App\Exceptions;

use App\Enums\ErrorCodeEnum;
use Throwable;

/**
 * Concrete throwable domain exception.
 * Use this for business-rule violations in Services.
 * PostService, HRJourneyService, ReportingService all throw this.
 */
class DomainException extends ApplicationException
{
    public function __construct(
        string $message,
        ErrorCodeEnum $errorCode = ErrorCodeEnum::VALIDATION_ERROR,
        ?int $statusCode = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode, $statusCode, $previous);
    }
}
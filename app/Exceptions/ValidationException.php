<?php

namespace App\Exceptions;

use App\Enums\ErrorCodeEnum;

class ValidationException extends ApplicationException
{
    protected array $errors = [];

    public function __construct(
        string $message = 'Validation failed',
        array $errors = [],
        ?ErrorCodeEnum $errorCode = null
    ) {
        $this->errors = $errors;
        $errorCode = $errorCode ?? ErrorCodeEnum::VALIDATION_FAILED;
        parent::__construct($message, $errorCode, 422);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

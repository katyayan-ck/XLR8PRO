<?php

namespace App\Exceptions;

use App\Enums\ErrorCodeEnum;
use Exception;
use Illuminate\Http\JsonResponse;
use Throwable;

abstract class ApplicationException extends Exception
{
    protected ErrorCodeEnum $errorCode;
    protected int $statusCode;
    protected array $errors = [];

    public function __construct(
        string $message,
        ErrorCodeEnum $errorCode = ErrorCodeEnum::SYSTEM_ERROR,
        ?int $statusCode = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode ?? $errorCode->statusCode();
    }

    public function getErrorCode(): ErrorCodeEnum
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setErrors(array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    public function toJsonResponse(): JsonResponse
    {
        $response = [
            'http_status' => $this->statusCode,
            'success' => false,
            'code' => $this->errorCode->value,
            'message' => $this->message,
            'timestamp' => now()->toIso8601String(),
        ];

        if (!empty($this->errors)) {
            $response['errors'] = $this->errors;
        }

        return response()->json($response, $this->statusCode);
    }
}

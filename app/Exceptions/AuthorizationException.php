<?php

namespace App\Exceptions;

use App\Enums\ErrorCodeEnum;

class AuthorizationException extends ApplicationException
{
    public function __construct(
        string $action = 'perform this action',
        ?string $resource = null,
        ?string $permission = null,
        ?Exception $previous = null
    ) {
        $message = "You are not authorized to {$action}";
        if ($permission) {
            $message = "You don't have permission to {$permission}";
        }
        if ($resource) {
            $message .= " on {$resource}";
        }
        $message .= '.';

        parent::__construct($message, ErrorCodeEnum::AUTH_FORBIDDEN, 403, $previous);
    }
}

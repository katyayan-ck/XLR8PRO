<?php

namespace App\Exceptions;

use App\Enums\ErrorCodeEnum;

/**
 * Permission Exception
 * 
 * Thrown when user doesn't have required permission for action.
 * Similar to AuthorizationException but for permission-specific scenarios.
 * 
 * HTTP Status: 403 (Forbidden)
 */
class PermissionException extends ApplicationException
{
    /**
     * Permission that was denied
     */
    private string $permission;

    /**
     * Resource on which permission was denied
     */
    private ?string $resource;

    /**
     * Constructor
     * 
     * @param string $permission Permission name
     * @param string|null $resource Resource name (optional)
     */
    public function __construct(
        string $permission = 'access',
        ?string $resource = null
    ) {
        $this->permission = $permission;
        $this->resource = $resource;

        $message = $resource
            ? "You don't have permission to {$permission} {$resource}"
            : "You don't have permission to {$permission}";

        parent::__construct(
            $message,
            ErrorCodeEnum::AUTH_FORBIDDEN,
            403
        );
    }

    /**
     * Get permission
     * 
     * @return string
     */
    public function getPermission(): string
    {
        return $this->permission;
    }

    /**
     * Get resource
     * 
     * @return string|null
     */
    public function getResource(): ?string
    {
        return $this->resource;
    }
}

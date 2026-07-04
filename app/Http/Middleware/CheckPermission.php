<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permission Checking Middleware
 * 
 * Usage in routes:
 * Route::get('/branches', [BranchController::class, 'index'])
 *     ->middleware('checkPermission:branch.view,branch.list');
 * 
 * Multiple permissions (OR logic - user needs at least one):
 * ->middleware('checkPermission:branch.view,branch.list')
 * 
 * Supports wildcards:
 * ->middleware('checkPermission:branch.*')
 */
class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = auth()->user();

        // No authenticated user
        if (!$user) {
            return $this->unauthorized($request);
        }

        // Check if user has any of the required permissions (OR logic)
        if (!$this->hasAnyPermission($user, $permissions)) {
            return $this->unauthorized($request);
        }

        return $next($request);
    }

    /**
     * Check if user has any of the required permissions
     *
     * @param  mixed  $user
     * @param  array  $permissions
     * @return bool
     */
    private function hasAnyPermission($user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->checkPermission($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has a specific permission (supports wildcards)
     *
     * @param  mixed  $user
     * @param  string  $permission
     * @return bool
     */
    private function checkPermission($user, string $permission): bool
    {
        // Remove any whitespace
        $permission = trim($permission);

        // Check for wildcard permission (e.g., branch.*)
        if (str_ends_with($permission, '.*')) {
            $prefix = substr($permission, 0, -2);
            return $user->hasPermissionTo($prefix . '.view') ||
                $user->hasPermissionTo($prefix . '.create') ||
                $user->hasPermissionTo($prefix . '.edit') ||
                $user->hasPermissionTo($prefix . '.delete');
        }

        // Standard permission check
        return $user->hasPermissionTo($permission);
    }

    /**
     * Return unauthorized response
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function unauthorized(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Unauthorized: Insufficient permissions',
                'status' => 403,
            ], 403);
        }

        abort(403, 'Unauthorized: You do not have permission to access this resource.');
    }
}

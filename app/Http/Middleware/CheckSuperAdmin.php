<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Super Admin Checking Middleware
 * 
 * Allows only users with super_admin or admin role
 * 
 * Usage in routes:
 * Route::patch('/branches/{id}', [BranchController::class, 'update'])
 *     ->middleware('checkSuperAdmin');
 */
class CheckSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Check if user is authenticated
        if (!$user) {
            return $this->unauthorized($request, 'Unauthenticated');
        }

        // Check if user has super admin role
        if (!$user->isSuperAdmin()) {
            return $this->unauthorized($request, 'Super admin access required');
        }

        return $next($request);
    }

    /**
     * Return unauthorized response
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $message
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function unauthorized(Request $request, string $message = 'Unauthorized'): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'status' => 403,
            ], 403);
        }

        abort(403, $message);
    }
}

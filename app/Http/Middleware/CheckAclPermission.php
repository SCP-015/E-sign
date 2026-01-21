<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAclPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$permissions
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthorizedResponse($request);
        }

        // Get current tenant ID from session or user
        $tenantId = session('current_tenant_id') ?? $user->current_tenant_id ?? null;

        // If no tenant context, allow access (personal mode)
        if (!$tenantId) {
            return $next($request);
        }

        // Check if user has trait
        if (!method_exists($user, 'hasPermissionInTenant')) {
            return $next($request);
        }

        // Check if user has any of the required permissions
        if (!$this->userHasPermissions($user, $permissions, $tenantId)) {
            return $this->forbiddenResponse($request, $permissions);
        }

        return $next($request);
    }

    /**
     * Check if user has any of the required permissions
     */
    private function userHasPermissions($user, array $permissions, string $tenantId): bool
    {
        foreach ($permissions as $permissionGroup) {
            // Support OR syntax: 'permission1|permission2'
            $permissionList = explode('|', $permissionGroup);

            foreach ($permissionList as $permission) {
                if ($user->hasPermissionInTenant($permission, $tenantId)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Return unauthorized response
     */
    private function unauthorizedResponse(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'data' => null
            ], 401);
        }

        return redirect()->route('login');
    }

    /**
     * Return forbidden response
     */
    private function forbiddenResponse(Request $request, array $permissions): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have the required permissions to access this resource.',
                'data' => [
                    'required_permissions' => $permissions
                ]
            ], 403);
        }

        return redirect()->back()->with('error', 'Anda tidak memiliki izin untuk mengakses halaman tersebut.');
    }
}

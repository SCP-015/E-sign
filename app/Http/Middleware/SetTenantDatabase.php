<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SetTenantDatabase
{
    /**
     * Handle an incoming request.
     * 
     * Middleware untuk switch database connection based on current tenant context.
     * Diambil dari session atau user's current_tenant_id.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $this->getCurrentTenantId($request);

        if ($tenantId) {
            // ... (tenant switches handle below)
            $dbName = $this->getTenantDatabaseName($tenantId);
            config(['database.connections.tenant.database' => $dbName]);
            DB::purge('tenant');
            config(['tenant.currentTenantId' => $tenantId]);
            Log::debug("Tenant context set (connection configured)", ['tenant_id' => $tenantId, 'db' => $dbName]);
        } else {
            // Personal mode - clear tenant context
            config(['tenant.currentTenantId' => null]);
            
            Log::debug("Personal mode - tenant context cleared");
        }

        // IMPORTANT: Default connection stays 'pgsql' (central)
        // This ensures User, OAuth, and other central models work correctly
        DB::setDefaultConnection('pgsql');

        $response = $next($request);

        // Cleanup: purge tenant connection after request
        if ($tenantId) {
            DB::purge('tenant');
        }

        return $response;
    }

    /**
     * Get current tenant ID dari session atau user.
     */
    protected function getCurrentTenantId(Request $request): ?string
    {
        // Priority 1: From header (stateless tenant context)
        // Only trust header when user is authenticated and is a member of the tenant.
        $headerTenantId = trim((string) $request->header('X-Tenant-Id', ''));
        if ($headerTenantId !== '') {
            $user = $request->user();
            if ($user) {
                $tenant = Tenant::find($headerTenantId);
                if ($tenant) {
                    $isMember = $tenant->tenantUsers()
                        ->where('user_id', $user->id)
                        ->exists();

                    if ($isMember) {
                        // Keep session in sync so downstream code using session() remains consistent.
                        try {
                            $request->session()->put('current_tenant_id', $headerTenantId);
                        } catch (\Throwable $e) {
                            // ignore
                        }

                        return $headerTenantId;
                    }
                }
            }
        }

        // Priority 2: From session (explicit tenant selection)
        if ($request->session()->has('current_tenant_id')) {
            $tenantId = $request->session()->get('current_tenant_id');
            return is_string($tenantId) && $tenantId !== '' ? $tenantId : null;
        }

        // Priority 3: From authenticated user's current_tenant_id
        $user = $request->user();
        if ($user && $user->current_tenant_id) {
            return $user->current_tenant_id;
        }

        return null;
    }

    /**
     * Get tenant database name dari tenant ID.
     */
    protected function getTenantDatabaseName(string $tenantId): string
    {
        $tenant = Tenant::find($tenantId);
        if ($tenant && !empty($tenant->tenancy_db_name)) {
            return (string) $tenant->tenancy_db_name;
        }

        $prefix = config('tenancy.database.prefix', 'tenant_');
        $cleanId = str_replace('-', '_', strtolower($tenantId));
        return "{$prefix}{$cleanId}";
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class SetCurrentTenantFromSlug
{
    /**
     * Set current tenant context based on {tenantSlug} route param.
     */
    public function handle(Request $request, Closure $next)
    {
        $slug = (string) $request->route('tenantSlug');

        $tenant = Tenant::where('slug', $slug)->first();
        if (!$tenant) {
            abort(404);
        }

        $user = $request->user();
        if ($user) {
            $membership = $tenant->tenantUsers()->where('user_id', $user->id)->first();
            if (!$membership) {
                abort(403, 'Anda bukan anggota organization ini.');
            }

            session(['current_tenant_id' => $tenant->id]);
            session()->save();

            if ($user->current_tenant_id !== $tenant->id) {
                $user->current_tenant_id = $tenant->id;
                $user->save();
            }
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\Auth;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = Auth::guard('api')->user() ?? $request->user();

        $tenant = $user ? $user->getCurrentTenant() : null;

        $organization = null;
        if ($tenant && $user) {
            $aclRole = $user->getRoleInTenant($tenant->id);
            $roleName = $aclRole ? $aclRole->name : ($tenant->pivot->role ?? 'member');
            
            $organization = [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'role' => $roleName,
            ];
        }

        return array_merge(parent::share($request), [
            'appName' => config('app.name'),
            'auth' => [
                'user' => $user,
                'organization' => $organization,
            ],
        ]);
    }
}

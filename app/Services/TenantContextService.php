<?php

namespace App\Services;

use App\Models\User;

class TenantContextService
{
    public function getCurrentContext(User $user): array
    {
        $tenant = $user->getCurrentTenant();

        if (!$tenant) {
            return [
                'status' => 'success',
                'data' => null,
                'message' => 'OK',
                'code' => 200,
                'mode' => 'personal',
            ];
        }

        $aclRole = $user->getRoleInTenant($tenant->id);
        $roleName = $aclRole ? $aclRole->name : ($tenant->pivot->role ?? 'member');

        return [
            'status' => 'success',
            'data' => [
                'tenant' => $tenant,
                'role' => $roleName,
            ],
            'message' => 'OK',
            'code' => 200,
            'mode' => 'organization',
        ];
    }

    public function switchContext(User $user, ?string $tenantId): array
    {
        if (empty($tenantId)) {
            session()->forget('current_tenant_id');
            session()->save();

            $user->current_tenant_id = null;
            $user->save();

            return [
                'status' => 'success',
                'data' => null,
                'message' => __('Switched to personal mode.'),
                'code' => 200,
                'mode' => 'personal',
            ];
        }

        $tenant = $user->tenants()->where('tenants.id', $tenantId)->first();
        if (!$tenant) {
            return [
                'status' => 'error',
                'data' => null,
                'message' => __('You are not a member of this organization.'),
                'code' => 403,
                'mode' => 'personal',
            ];
        }

        session(['current_tenant_id' => $tenantId]);
        session()->save();

        $user->current_tenant_id = $tenantId;
        $user->save();

        $aclRole = $user->getRoleInTenant($tenant->id);
        $roleName = $aclRole ? $aclRole->name : ($tenant->pivot->role ?? 'member');

        return [
            'status' => 'success',
            'data' => [
                'tenant' => $tenant,
                'role' => $roleName,
            ],
            'message' => __('Switched to :tenant', ['tenant' => $tenant->name]),
            'code' => 200,
            'mode' => 'organization',
        ];
    }
}

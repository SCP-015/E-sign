<?php

namespace App\Traits;

use App\Models\AclRole;
use App\Models\AclPermission;
use Illuminate\Support\Facades\DB;

trait HasAclPermissions
{
    /**
     * Get user's role in a specific tenant
     */
    public function getRoleInTenant(?string $tenantId): ?AclRole
    {
        if (!$tenantId) {
            return null;
        }

        $roleRecord = DB::table('acl_model_has_roles')
            ->where('model_type', get_class($this))
            ->where('model_id', $this->id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$roleRecord) {
            return null;
        }

        return AclRole::find($roleRecord->role_id);
    }

    /**
     * Assign a role to user in a tenant
     */
    public function assignRoleInTenant(string $roleName, string $tenantId): bool
    {
        $normalizedRoleName = strtolower($roleName);
        if ($normalizedRoleName === 'user') {
            $normalizedRoleName = 'member';
        }

        $role = AclRole::where('name', $normalizedRoleName)->first();
        if (!$role) {
            return false;
        }

        // Remove existing role in this tenant first
        DB::table('acl_model_has_roles')
            ->where('model_type', get_class($this))
            ->where('model_id', $this->id)
            ->where('tenant_id', $tenantId)
            ->delete();

        // Assign new role
        DB::table('acl_model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => get_class($this),
            'model_id' => $this->id,
            'tenant_id' => $tenantId,
        ]);

        return true;
    }

    /**
     * Remove user's role in a tenant
     */
    public function removeRoleInTenant(string $tenantId): bool
    {
        return DB::table('acl_model_has_roles')
            ->where('model_type', get_class($this))
            ->where('model_id', $this->id)
            ->where('tenant_id', $tenantId)
            ->delete() > 0;
    }

    /**
     * Check if user has a specific role in tenant
     */
    public function hasRoleInTenant(string $roleName, string $tenantId): bool
    {
        $role = $this->getRoleInTenant($tenantId);
        return $role && $role->name === $roleName;
    }

    /**
     * Check if user has a specific permission in tenant
     */
    public function hasPermissionInTenant(string $permissionName, ?string $tenantId): bool
    {
        if (!$tenantId) {
            return false;
        }

        $role = $this->getRoleInTenant($tenantId);
        if (!$role) {
            return false;
        }

        // Owner has all permissions
        if ($role->name === 'owner') {
            return true;
        }

        return $role->hasPermission($permissionName);
    }

    /**
     * Get all permissions for user in a tenant
     */
    public function getPermissionsInTenant(?string $tenantId): array
    {
        if (!$tenantId) {
            return [];
        }

        $role = $this->getRoleInTenant($tenantId);
        if (!$role) {
            return [];
        }

        // Owner gets all permissions
        if ($role->name === 'owner') {
            return AclPermission::pluck('name')->toArray();
        }

        return $role->permissions()->pluck('name')->toArray();
    }

    /**
     * Check if user has any of the given permissions in tenant
     */
    public function hasAnyPermissionInTenant(array $permissions, ?string $tenantId): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermissionInTenant($permission, $tenantId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all of the given permissions in tenant
     */
    public function hasAllPermissionsInTenant(array $permissions, ?string $tenantId): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermissionInTenant($permission, $tenantId)) {
                return false;
            }
        }
        return true;
    }
}

<?php

namespace App\Traits;

use App\Models\AclRole;
use App\Models\AclPermission;
use Illuminate\Support\Facades\DB;

trait HasAclPermissions
{
    /**
     * Get user's role in a specific tenant.
     * 
     * In multi-DB architecture, ACL tables are in tenant database.
     * Personal mode has NO ACL - returns null.
     */
    public function getRoleInTenant(?string $tenantId): ?AclRole
    {
        if (!$tenantId) {
            return null; // Personal mode - no ACL
        }

        // Switch to tenant database
        try {
            $dbMgr = app(\App\Services\TenantDatabaseManager::class);
            $dbName = $dbMgr->getTenantDatabaseName($tenantId);
            
            config(['database.connections.tenant.database' => $dbName]);
            DB::purge('tenant');

            $roleRecord = DB::connection('tenant')->table('acl_model_has_roles')
                ->where('model_type', 'App\Models\User')
                ->where('model_id', $this->id)
                ->first();

            if (!$roleRecord) {
                return null;
            }

            // Return proper Eloquent model, not stdClass
            return \App\Models\AclRole::on('tenant')->find($roleRecord->role_id);
        } catch (\PDOException $e) {
            // If database doesn't exist or connection fails, return null
            \Illuminate\Support\Facades\Log::warning("Could not connect to tenant database for tenant {$tenantId}: " . $e->getMessage());
            return null;
        } finally {
            // Cleanup - ensure connection is reset
            DB::purge('tenant');
            DB::setDefaultConnection('pgsql');
        }
    }

    /**
     * Assign a role to user in a tenant
     */
    public function assignRoleInTenant(string $roleName, string $tenantId): bool
    {
        if (!$tenantId) {
            return false; // Cannot assign role in personal mode
        }

        $normalizedRoleName = strtolower($roleName);
        if ($normalizedRoleName === 'user') {
            $normalizedRoleName = 'member';
        }

        // Switch to tenant database
        try {
            $dbMgr = app(\App\Services\TenantDatabaseManager::class);
            $dbName = $dbMgr->getTenantDatabaseName($tenantId);
            
            config(['database.connections.tenant.database' => $dbName]);
            DB::purge('tenant');

            $role = DB::connection('tenant')->table('acl_roles')
                ->where('name', $normalizedRoleName)
                ->first();

            if (!$role) {
                return false;
            }

            // Remove existing role in this tenant first
            DB::connection('tenant')->table('acl_model_has_roles')
                ->where('model_type', 'App\Models\User')
                ->where('model_id', $this->id)
                ->delete();

            // Assign new role (no tenant_id column in multi-DB)
            DB::connection('tenant')->table('acl_model_has_roles')->insert([
                'role_id' => $role->id,
                'model_type' => 'App\Models\User',
                'model_id' => $this->id,
            ]);

            return true;
        } catch (\PDOException $e) {
            \Illuminate\Support\Facades\Log::warning("Could not assign role in tenant database for tenant {$tenantId}. Database may be missing: " . $e->getMessage());
            return false;
        } finally {
            DB::purge('tenant');
            DB::setDefaultConnection('pgsql');
        }
    }

    /**
     * Remove user's role in a tenant
     */
    public function removeRoleInTenant(string $tenantId): bool
    {
        if (!$tenantId) {
            return false;
        }

        try {
            $dbMgr = app(\App\Services\TenantDatabaseManager::class);
            $dbName = $dbMgr->getTenantDatabaseName($tenantId);
            
            config(['database.connections.tenant.database' => $dbName]);
            DB::purge('tenant');

            $deleted = DB::connection('tenant')->table('acl_model_has_roles')
                ->where('model_type', 'App\Models\User')
                ->where('model_id', $this->id)
                ->delete();

            return $deleted > 0;
        } catch (\PDOException $e) {
            return false;
        } finally {
            DB::purge('tenant');
            DB::setDefaultConnection('pgsql');
        }
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

        try {
            $role = $this->getRoleInTenant($tenantId);
            if (!$role) {
                return [];
            }

            // Owner gets all permissions (ACL tables live in tenant DB)
            if ($role->name === 'owner') {
                return AclPermission::on('tenant')->pluck('name')->toArray();
            }

            return $role->permissions()->pluck('name')->toArray();
        } catch (\Throwable $e) {
            // Do not break profile endpoint if ACL tables are missing in tenant DB.
            return [];
        }
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

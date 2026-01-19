<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Sinkronisasi role dari tenant_users ke acl_model_has_roles
     */
    public function up(): void
    {
        // Get all tenant_users records
        $tenantUsers = DB::table('tenant_users')->get();

        foreach ($tenantUsers as $tenantUser) {
            // Map role from tenant_users to ACL role
            $aclRoleName = $this->mapRoleToAcl($tenantUser->role, $tenantUser->is_owner);

            // Get ACL role ID
            $aclRole = DB::table('acl_roles')->where('name', $aclRoleName)->first();
            if (!$aclRole) {
                continue;
            }

            // Insert into acl_model_has_roles (ignore if exists)
            DB::table('acl_model_has_roles')->updateOrInsert(
                [
                    'role_id' => $aclRole->id,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $tenantUser->user_id,
                    'tenant_id' => $tenantUser->tenant_id,
                ],
                [
                    'role_id' => $aclRole->id,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $tenantUser->user_id,
                    'tenant_id' => $tenantUser->tenant_id,
                ]
            );
        }
    }

    /**
     * Map tenant_users role to ACL role name
     */
    private function mapRoleToAcl(?string $role, bool $isOwner): string
    {
        // Owner always gets 'owner' role
        if ($isOwner) {
            return 'owner';
        }

        // Map existing roles
        return match (strtolower($role ?? 'member')) {
            'admin', 'administrator', 'manager' => 'admin',
            default => 'member',
        };
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove all ACL role assignments that came from tenant_users
        $tenantUsers = DB::table('tenant_users')->get();

        foreach ($tenantUsers as $tenantUser) {
            DB::table('acl_model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $tenantUser->user_id)
                ->where('tenant_id', $tenantUser->tenant_id)
                ->delete();
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Re-sync role dari tenant_users ke acl_model_has_roles (idempotent).
     */
    public function up(): void
    {
        $tenantUsers = DB::table('tenant_users')->get();

        foreach ($tenantUsers as $tenantUser) {
            $aclRoleName = $this->mapRoleToAcl($tenantUser->role, (bool) $tenantUser->is_owner);

            $aclRole = DB::table('acl_roles')->where('name', $aclRoleName)->first();
            if (!$aclRole) {
                continue;
            }

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

    private function mapRoleToAcl(?string $role, bool $isOwner): string
    {
        if ($isOwner) {
            return 'owner';
        }

        return match (strtolower($role ?? 'member')) {
            'admin', 'administrator' => 'admin',
            'manager' => 'admin',
            default => 'member',
        };
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tidak melakukan rollback karena ini hanya re-sync data.
    }
};

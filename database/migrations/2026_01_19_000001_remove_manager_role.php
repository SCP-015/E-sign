<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Normalize legacy 'manager' role to 'admin' and remove 'manager' from allowed roles.
     */
    public function up(): void
    {
        // Normalize any legacy 'user' role to new 'member'
        DB::table('tenant_users')->where('role', 'user')->update(['role' => 'member']);
        if (Schema::hasTable('tenant_invitations')) {
            DB::table('tenant_invitations')->where('role', 'user')->update(['role' => 'member']);
        }

        // 1) Normalize tenant_users.role
        DB::table('tenant_users')
            ->where('role', 'manager')
            ->update(['role' => 'admin']);

        // 2) Normalize tenant_invitations.role
        if (Schema::hasTable('tenant_invitations')) {
            DB::table('tenant_invitations')
                ->where('role', 'manager')
                ->update(['role' => 'admin']);
        }

        // 3) Normalize ACL model-role assignments (manager -> admin)
        $managerRoleId = DB::table('acl_roles')->where('name', 'manager')->value('id');
        $adminRoleId = DB::table('acl_roles')->where('name', 'admin')->value('id');

        if ($managerRoleId && $adminRoleId) {
            $managerAssignments = DB::table('acl_model_has_roles')
                ->where('role_id', $managerRoleId)
                ->get();

            foreach ($managerAssignments as $row) {
                // Delete manager row first (avoid primary key conflicts)
                DB::table('acl_model_has_roles')
                    ->where('role_id', $managerRoleId)
                    ->where('model_type', $row->model_type)
                    ->where('model_id', $row->model_id)
                    ->where('tenant_id', $row->tenant_id)
                    ->delete();

                // Upsert admin role for the same tenant/user
                DB::table('acl_model_has_roles')->updateOrInsert(
                    [
                        'role_id' => $adminRoleId,
                        'model_type' => $row->model_type,
                        'model_id' => $row->model_id,
                        'tenant_id' => $row->tenant_id,
                    ],
                    [
                        'role_id' => $adminRoleId,
                        'model_type' => $row->model_type,
                        'model_id' => $row->model_id,
                        'tenant_id' => $row->tenant_id,
                    ]
                );
            }

            // Remove role-permission mappings and the manager role itself
            DB::table('acl_role_has_permissions')->where('role_id', $managerRoleId)->delete();
            DB::table('acl_roles')->where('id', $managerRoleId)->delete();
        }

        // 4) Drop 'manager' from role constraints (PostgreSQL check constraints)
        DB::statement("ALTER TABLE tenant_users DROP CONSTRAINT IF EXISTS tenant_users_role_check");
        DB::statement("ALTER TABLE tenant_users ADD CONSTRAINT tenant_users_role_check CHECK (role IN ('owner', 'admin', 'member'))");

        if (Schema::hasTable('tenant_invitations')) {
            DB::statement("ALTER TABLE tenant_invitations DROP CONSTRAINT IF EXISTS tenant_invitations_role_check");
            DB::statement("ALTER TABLE tenant_invitations ADD CONSTRAINT tenant_invitations_role_check CHECK (role IN ('admin', 'member'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We intentionally do not recreate the 'manager' role.
    }
};

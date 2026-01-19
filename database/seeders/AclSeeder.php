<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AclSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedRoles();
        $this->seedRolePermissions();
    }

    /**
     * Seed all permissions from config
     */
    private function seedPermissions(): void
    {
        $permissionGroups = config('permissions');
        $permissions = [];
        $now = now();

        foreach ($permissionGroups as $group => $items) {
            // Skip role permission mappings
            if (str_ends_with($group, '_permissions')) {
                continue;
            }

            if (is_array($items)) {
                foreach ($items as $key => $permission) {
                    if (is_string($permission)) {
                        $permissions[] = [
                            'name' => $permission,
                            'guard_name' => 'api',
                            'description' => ucfirst(str_replace(['_', '.'], ' ', $permission)),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }
        }

        // Insert permissions (ignore duplicates)
        foreach ($permissions as $permission) {
            DB::table('acl_permissions')->updateOrInsert(
                ['name' => $permission['name']],
                $permission
            );
        }
    }

    /**
     * Seed default roles
     */
    private function seedRoles(): void
    {
        $roles = [
            [
                'name' => 'owner',
                'guard_name' => 'api',
                'description' => 'Organization owner with full access',
            ],
            [
                'name' => 'admin',
                'guard_name' => 'api',
                'description' => 'Administrator with almost full access',
            ],
            [
                'name' => 'member',
                'guard_name' => 'api',
                'description' => 'Regular user with basic access',
            ],
        ];

        $now = now();
        foreach ($roles as $role) {
            DB::table('acl_roles')->updateOrInsert(
                ['name' => $role['name'], 'guard_name' => $role['guard_name']],
                array_merge($role, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    /**
     * Seed role-permission mappings
     */
    private function seedRolePermissions(): void
    {
        $rolePermissionMappings = [
            'owner' => config('permissions.owner_permissions', []),
            'admin' => config('permissions.admin_permissions', []),
            'member' => config('permissions.member_permissions', []),
        ];

        foreach ($rolePermissionMappings as $roleName => $permissionNames) {
            $role = DB::table('acl_roles')->where('name', $roleName)->first();
            if (!$role) {
                continue;
            }

            foreach ($permissionNames as $permissionName) {
                $permission = DB::table('acl_permissions')->where('name', $permissionName)->first();
                if (!$permission) {
                    continue;
                }

                DB::table('acl_role_has_permissions')->updateOrInsert(
                    [
                        'role_id' => $role->id,
                        'permission_id' => $permission->id,
                    ]
                );
            }
        }
    }
}

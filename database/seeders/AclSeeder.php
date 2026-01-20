<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
                            'id' => $permissionId = (string) Str::ulid(),
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

        // Insert permissions (ignore duplicates by checking name)
        foreach ($permissions as $permission) {
            $exists = DB::table('acl_permissions')->where('name', $permission['name'])->exists();
            if (!$exists) {
                DB::table('acl_permissions')->insert($permission);
            }
        }
    }

    /**
     * Seed default roles
     */
    private function seedRoles(): void
    {
        $roles = [
            [
                'id' => (string) Str::ulid(),
                'name' => 'owner',
                'guard_name' => 'api',
                'description' => 'Organization owner with full access',
            ],
            [
                'id' => (string) Str::ulid(),
                'name' => 'admin',
                'guard_name' => 'api',
                'description' => 'Administrator with almost full access',
            ],
            [
                'id' => (string) Str::ulid(),
                'name' => 'member',
                'guard_name' => 'api',
                'description' => 'Regular user with basic access',
            ],
        ];

        $now = now();
        foreach ($roles as $role) {
            $exists = DB::table('acl_roles')->where('name', $role['name'])->where('guard_name', $role['guard_name'])->exists();
            if (!$exists) {
                DB::table('acl_roles')->insert(array_merge($role, ['created_at' => $now, 'updated_at' => $now]));
            }
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

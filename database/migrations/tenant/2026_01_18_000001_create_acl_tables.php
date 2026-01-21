<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabel permissions
        Schema::create('acl_permissions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->unique();
            $table->string('guard_name')->default('api');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Tabel roles
        Schema::create('acl_roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('guard_name')->default('api');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        // Tabel pivot role_has_permissions
        Schema::create('acl_role_has_permissions', function (Blueprint $table) {
            $table->ulid('permission_id');
            $table->ulid('role_id');

            $table->foreign('permission_id')
                ->references('id')
                ->on('acl_permissions')
                ->onDelete('cascade');

            $table->foreign('role_id')
                ->references('id')
                ->on('acl_roles')
                ->onDelete('cascade');

            $table->primary(['permission_id', 'role_id']);
        });

        // Tabel pivot model_has_roles
        Schema::create('acl_model_has_roles', function (Blueprint $table) {
            $table->ulid('role_id');
            $table->string('model_type');
            $table->ulid('model_id');
            
            $table->foreign('role_id')
                ->references('id')
                ->on('acl_roles')
                ->onDelete('cascade');

            $table->index(['model_id', 'model_type']);
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_primary');
        });

        // Tabel pivot model_has_permissions
        Schema::create('acl_model_has_permissions', function (Blueprint $table) {
            $table->ulid('permission_id');
            $table->string('model_type');
            $table->ulid('model_id');
            $table->ulid('tenant_id')->nullable()->index();

            $table->foreign('permission_id')
                ->references('id')
                ->on('acl_permissions')
                ->onDelete('cascade');

            $table->index(['model_id', 'model_type']);
            $table->primary(['permission_id', 'model_id', 'model_type', 'tenant_id'], 'model_has_permissions_primary');
        });

        // Auto-seed roles, permissions, dan mappings
        $this->seedAclData();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acl_model_has_permissions');
        Schema::dropIfExists('acl_model_has_roles');
        Schema::dropIfExists('acl_role_has_permissions');
        Schema::dropIfExists('acl_roles');
        Schema::dropIfExists('acl_permissions');
    }

    /**
     * Seed ACL data otomatis ketika migration dijalankan
     */
    private function seedAclData(): void
    {
        $now = now();
        
        // 1. Seed Permissions dari config
        $this->seedPermissions($now);
        
        // 2. Seed Roles
        $this->seedRoles($now);
        
        // 3. Seed Role-Permission Mappings
        $this->seedRolePermissions();
    }

    private function seedPermissions($now): void
    {
        $permissionGroups = config('permissions');
        $permissions = [];

        foreach ($permissionGroups as $group => $items) {
            if (str_ends_with($group, '_permissions')) {
                continue;
            }

            if (is_array($items)) {
                foreach ($items as $key => $permission) {
                    if (is_string($permission)) {
                        $permissions[] = [
                            'id' => (string) Str::ulid(),
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

        foreach ($permissions as $permission) {
            DB::table('acl_permissions')->insertOrIgnore($permission);
        }
    }

    private function seedRoles($now): void
    {
        $roles = [
            [
                'id' => (string) Str::ulid(),
                'name' => 'owner',
                'guard_name' => 'api',
                'description' => 'Organization owner with full access',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'name' => 'admin',
                'guard_name' => 'api',
                'description' => 'Administrator with almost full access',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'name' => 'member',
                'guard_name' => 'api',
                'description' => 'Regular user with basic access',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($roles as $role) {
            DB::table('acl_roles')->insertOrIgnore($role);
        }
    }

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

                DB::table('acl_role_has_permissions')->insertOrIgnore([
                    'role_id' => $role->id,
                    'permission_id' => $permission->id,
                ]);
            }
        }
    }
};

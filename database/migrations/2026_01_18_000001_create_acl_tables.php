<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabel permissions
        Schema::create('acl_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('guard_name')->default('api');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Tabel roles
        Schema::create('acl_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('api');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        // Tabel pivot role_has_permissions
        Schema::create('acl_role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

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

        // Tabel pivot model_has_roles (user punya role di tenant tertentu)
        Schema::create('acl_model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('tenant_id')->nullable()->index(); // untuk multi-tenant

            $table->foreign('role_id')
                ->references('id')
                ->on('acl_roles')
                ->onDelete('cascade');

            $table->index(['model_id', 'model_type']);
            $table->primary(['role_id', 'model_id', 'model_type', 'tenant_id'], 'model_has_roles_primary');
        });

        // Tabel pivot model_has_permissions (user punya permission langsung)
        Schema::create('acl_model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('tenant_id')->nullable()->index(); // untuk multi-tenant

            $table->foreign('permission_id')
                ->references('id')
                ->on('acl_permissions')
                ->onDelete('cascade');

            $table->index(['model_id', 'model_type']);
            $table->primary(['permission_id', 'model_id', 'model_type', 'tenant_id'], 'model_has_permissions_primary');
        });
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
};

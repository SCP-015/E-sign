<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan 'owner' ke enum role di tenant_users
     */
    public function up(): void
    {
        // Untuk PostgreSQL, kita perlu mengubah constraint enum
        DB::statement("ALTER TABLE tenant_users DROP CONSTRAINT IF EXISTS tenant_users_role_check");
        DB::statement("ALTER TABLE tenant_users ADD CONSTRAINT tenant_users_role_check CHECK (role IN ('owner', 'admin', 'member'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan constraint ke nilai semula
        DB::statement("ALTER TABLE tenant_users DROP CONSTRAINT IF EXISTS tenant_users_role_check");
        DB::statement("ALTER TABLE tenant_users ADD CONSTRAINT tenant_users_role_check CHECK (role IN ('admin', 'member'))");
    }
};

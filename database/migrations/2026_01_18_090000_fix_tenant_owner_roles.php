<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Perbaiki role untuk tenant owners yang masih 'admin' menjadi 'owner'
     */
    public function up(): void
    {
        // Update semua tenant_users yang is_owner = true tapi role masih 'admin'
        DB::table('tenant_users')
            ->where('is_owner', true)
            ->where('role', '!=', 'owner')
            ->update(['role' => 'owner']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan ke admin (jika diperlukan rollback)
        DB::table('tenant_users')
            ->where('is_owner', true)
            ->where('role', 'owner')
            ->update(['role' => 'admin']);
    }
};

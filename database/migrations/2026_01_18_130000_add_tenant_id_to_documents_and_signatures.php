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
        Schema::table('documents', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('user_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'user_id'], 'idx_tenant_documents');
        });

        Schema::table('signatures', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('user_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'user_id'], 'idx_tenant_signatures');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex('idx_tenant_documents');
            $table->dropColumn('tenant_id');
        });

        Schema::table('signatures', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex('idx_tenant_signatures');
            $table->dropColumn('tenant_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');

        Schema::create('quota_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // Logical reference to tenant (no physical FK - cross-DB)
            $table->ulid('tenant_id');
            $table->index('tenant_id'); // Index for performance
            
            $table->integer('max_documents_per_user')->default(50);
            $table->integer('max_signatures_per_user')->default(100);
            $table->integer('max_document_size_mb')->default(10);
            $table->integer('max_total_storage_mb')->default(500);
            $table->timestamps();

            $table->unique('tenant_id');
        });

        Schema::create('user_quota_usage', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // Logical references (no physical FK - cross-DB)
            $table->ulid('user_id');
            $table->ulid('tenant_id');
            
            // Indexes for performance (critical without FK)
            $table->index('user_id');
            $table->index('tenant_id');
            
            $table->integer('documents_uploaded')->default(0);
            $table->integer('signatures_created')->default(0);
            $table->integer('storage_used_mb')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_quota_usage');
        Schema::dropIfExists('quota_settings');
    }
};

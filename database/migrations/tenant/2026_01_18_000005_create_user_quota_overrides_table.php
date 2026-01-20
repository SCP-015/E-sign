<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_quota_overrides', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // Logical references (no physical FK - cross-DB)
            $table->ulid('user_id');
            $table->ulid('tenant_id');
            
            // Indexes for performance
            $table->index('user_id');
            $table->index('tenant_id');

            $table->integer('max_documents_per_user')->nullable();
            $table->integer('max_signatures_per_user')->nullable();
            $table->integer('max_total_storage_mb')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_quota_overrides');
    }
};

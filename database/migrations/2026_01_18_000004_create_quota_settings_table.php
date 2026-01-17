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
        Schema::create('quota_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->integer('max_documents_per_user')->default(50);
            $table->integer('max_signatures_per_user')->default(100);
            $table->integer('max_document_size_mb')->default(10);
            $table->integer('max_total_storage_mb')->default(500);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->unique('tenant_id');
        });

        Schema::create('user_quota_usage', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('tenant_id');
            $table->integer('documents_uploaded')->default(0);
            $table->integer('signatures_created')->default(0);
            $table->integer('storage_used_mb')->default(0);
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

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

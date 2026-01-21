<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * FINAL SQUASHED MIGRATION untuk tabel documents.
     * Tabel ini ada di KEDUA database (Central untuk Personal, Tenant untuk Org).
     * Schema IDENTIK untuk memudahkan logic.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // User ownership
            $table->ulid('user_id');
            $table->index('user_id'); // Index tanpa FK constraint
            
            // Basic document info
            $table->string('title');
            $table->string('file_path');
            $table->string('original_filename')->nullable();
            $table->bigInteger('file_size')->nullable(); // Deprecated, use file_size_bytes
            $table->string('mime_type')->nullable();
            
            // File details (MVP flow)
            $table->string('file_type')->default('pdf');
            $table->integer('file_size_bytes')->nullable();
            $table->integer('page_count')->nullable();
            
            // Signing flow paths
            $table->string('signed_path')->nullable();
            $table->string('final_pdf_path')->nullable();
            
            // Verification & status
            $table->string('verify_token')->nullable()->unique();
            $table->string('status')->default('DRAFT'); // DRAFT, IN_PROGRESS, COMPLETED
            $table->string('signing_mode')->nullable(); // sequential, parallel, etc.
            
            // Timestamps
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('status');
            $table->index('verify_token');
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

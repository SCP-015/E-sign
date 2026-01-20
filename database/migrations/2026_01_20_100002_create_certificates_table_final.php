<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * FINAL SQUASHED MIGRATION untuk tabel certificates.
     * Tabel ini ada di KEDUA database (Central untuk Personal, Tenant untuk Org).
     * Tenant certificates issued from tenant Root CA.
     */
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // User ownership
            $table->ulid('user_id');
            $table->index('user_id');
            
            // Root CA reference (nullable for personal mode)
            $table->ulid('root_ca_id')->nullable();
            $table->index('root_ca_id');
            
            // Certificate identification
            $table->string('certificate_number')->nullable()->unique();
            
            // Certificate files
            $table->text('public_key_path');
            $table->text('certificate_path');
            $table->text('private_key_path'); // Encrypted storage
            
            // Certificate details (from x509 parsing)
            $table->string('subject_name')->nullable();
            $table->string('issuer_name')->nullable();
            $table->string('serial_number')->nullable();
            
            // Validity period
            $table->dateTime('issued_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            
            // Status
            $table->string('status')->default('active'); // active, inactive, revoked, expired
            
            $table->timestamps();
            
            // Indexes
            $table->index('status');
            $table->index(['user_id', 'status']);
            $table->index(['issued_at', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};

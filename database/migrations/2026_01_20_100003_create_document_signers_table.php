<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * FINAL SQUASHED MIGRATION untuk tabel document_signers.
     * Tabel ini ada di KEDUA database (Central untuk Personal, Tenant untuk Org).
     */
    public function up(): void
    {
        Schema::create('document_signers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // Internal FK - documents is in same DB
            $table->ulid('document_id');
            
            // Logical reference - users is in central DB (no physical FK)
            $table->ulid('user_id')->nullable();
            $table->index('user_id'); // Index for performance

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            
            // Signer identification
            $table->string('name');
            $table->string("email")->nullable();
            
            // Invitations meta
            $table->string("invite_token")->nullable()->unique();
            $table->timestamp('invite_expires_at')->nullable();
            $table->timestamp('invite_accepted_at')->nullable();

            // Signing flow
            $table->integer('order')->nullable();
            $table->enum('status', ['PENDING', 'SIGNED'])->default('PENDING');
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
            
            // Performance indexes
            $table->index(['document_id', 'status']);
            $table->index(['document_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_signers');
    }
};

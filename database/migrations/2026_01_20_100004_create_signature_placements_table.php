<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * FINAL SQUASHED MIGRATION untuk tabel signature_placements.
     * Tabel ini ada di KEDUA database (Central untuk Personal, Tenant untuk Org).
     */
    public function up(): void
    {
        Schema::create('signature_placements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // Fks within the same DB
            $table->ulid('document_id');
            $table->ulid('signer_id');
            $table->ulid('signature_id')->nullable();

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('signer_id')->references('id')->on('document_signers')->onDelete('cascade');
            $table->foreign('signature_id')->references('id')->on('signatures')->onDelete('cascade');

            // Placement coordinates & page
            $table->integer('page');
            $table->float('x');
            $table->float('y');
            $table->float('w');
            $table->float('h');
            $table->timestamps();
            
            // Performance indexes
            $table->index(['document_id', 'signer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signature_placements');
    }
};

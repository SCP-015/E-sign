<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * FINAL SQUASHED MIGRATION untuk tabel signatures.
     * Tabel ini ada di KEDUA database (Central untuk Personal, Tenant untuk Org).
     * User bisa membawa signature kemana saja (portable).
     */
    public function up(): void
    {
        Schema::create('signatures', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // User ownership
            $table->ulid('user_id');
            $table->index('user_id');
            
            // Signature data (Synced with model/service)
            $table->string('name')->default('My Signature');
            $table->string('image_path');
            $table->string('image_type')->default('png'); // png, svg
            
            // Metadata
            $table->boolean('is_default')->default(false);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signatures');
    }
};

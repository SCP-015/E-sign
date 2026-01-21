<?php

declare(strict_types=1);

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
        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // Organization details
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            
            // Owner
            $table->ulid('owner_id');
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
            
            // Plan & settings
            $table->string('plan')->default('free');
            
            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

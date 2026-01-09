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
        Schema::create('signature_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('signer_id')->constrained('document_signers')->onDelete('cascade');
            $table->foreignId('signature_id')->constrained()->onDelete('cascade');
            $table->integer('page');
            $table->float('x');
            $table->float('y');
            $table->float('w');
            $table->float('h');
            $table->timestamps();
            
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signing_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('certificate_id')->nullable();
            $table->string('certificate_number')->nullable();
            $table->string('certificate_fingerprint_sha256', 128)->nullable();
            $table->string('certificate_serial')->nullable();
            $table->text('certificate_subject')->nullable();
            $table->text('certificate_issuer')->nullable();
            $table->timestamp('certificate_not_before')->nullable();
            $table->timestamp('certificate_not_after')->nullable();
            $table->longText('certificate_pem')->nullable();

            $table->timestamp('signed_at')->nullable();

            $table->string('tsa_url')->nullable();
            $table->timestamp('tsa_at')->nullable();
            $table->longText('tsa_token')->nullable();

            $table->longText('ocsp_response')->nullable();
            $table->longText('crl')->nullable();

            $table->timestamps();

            $table->index(['document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signing_evidences');
    }
};

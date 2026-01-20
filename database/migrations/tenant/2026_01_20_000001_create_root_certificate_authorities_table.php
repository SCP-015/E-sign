<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabel ini menyimpan Root Certificate Authority per tenant.
     * Setiap tenant memiliki Root CA sendiri yang digunakan untuk
     * menandatangani user certificates dalam tenant tersebut.
     */
    public function up(): void
    {
        Schema::create('root_certificate_authorities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // Certificate Authority Information
            $table->string('ca_name'); // Common Name untuk CA
            $table->text('certificate_path'); // Path ke public certificate (.crt)
            $table->text('private_key_path'); // Path ke private key (.key) - ENCRYPTED
            $table->text('public_key_path'); // Path ke public key (.pub)
            
            // Distinguished Name (DN) - disimpan untuk reference
            $table->string('dn_country', 2)->default('ID');
            $table->string('dn_state')->nullable();
            $table->string('dn_locality')->nullable(); // City
            $table->string('dn_organization'); // Company name
            $table->string('dn_organizational_unit')->nullable();
            $table->string('dn_common_name'); // CA Common Name
            
            // Certificate Validity
            $table->dateTime('valid_from');
            $table->dateTime('valid_until'); // Default: 10 years
            
            // CA Metadata
            $table->boolean('is_self_signed')->default(true);
            $table->integer('key_size')->default(4096); // RSA key size
            $table->string('signature_algorithm')->default('sha256');
            $table->string('status')->default('active'); // active, revoked, expired
            
            // Serial number untuk track certificate issuance
            $table->bigInteger('last_serial_number')->default(1000);
            
            // Additional metadata (extensions, policies, etc.)
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('status');
            $table->index(['valid_from', 'valid_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('root_certificate_authorities');
    }
};

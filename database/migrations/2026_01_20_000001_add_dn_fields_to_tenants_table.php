<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Menambahkan fields untuk Distinguished Name (DN) yang digunakan
     * untuk generate Root CA certificate per tenant.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Company/Organization Information untuk DN
            $table->string('company_legal_name')->nullable()->after('name');
            $table->string('company_country', 2)->default('ID')->after('company_legal_name'); // ISO 3166-1 alpha-2
            $table->string('company_state')->nullable()->after('company_country');
            $table->string('company_city')->nullable()->after('company_state');
            $table->string('company_address')->nullable()->after('company_city');
            $table->string('company_postal_code')->nullable()->after('company_address');
            $table->string('company_organization_unit')->nullable()->after('company_postal_code');
            
            // Root CA reference (akan diisi setelah CA dibuat)
            $table->boolean('has_root_ca')->default(false)->after('plan');
            $table->timestamp('root_ca_created_at')->nullable()->after('has_root_ca');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'company_legal_name',
                'company_country',
                'company_state',
                'company_city',
                'company_address',
                'company_postal_code',
                'company_organization_unit',
                'has_root_ca',
                'root_ca_created_at',
            ]);
        });
    }
};

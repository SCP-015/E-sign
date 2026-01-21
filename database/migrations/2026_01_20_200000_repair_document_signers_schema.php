<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Memperbaiki schema document_signers yang tertinggal di Central DB.
     */
    public function up(): void
    {
        if (Schema::hasTable('document_signers')) {
            Schema::table('document_signers', function (Blueprint $table) {
                if (!Schema::hasColumn('document_signers', 'invite_token')) {
                    $table->string("invite_token")->nullable()->unique()->after('email');
                }
                if (!Schema::hasColumn('document_signers', 'invite_expires_at')) {
                    $table->timestamp('invite_expires_at')->nullable()->after('invite_token');
                }
                if (!Schema::hasColumn('document_signers', 'invite_accepted_at')) {
                    $table->timestamp('invite_accepted_at')->nullable()->after('invite_expires_at');
                }
                
                // Ensure email exists (just in case)
                if (!Schema::hasColumn('document_signers', 'email')) {
                    $table->string("email")->nullable()->after('name');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropColumn(['invite_token', 'invite_expires_at', 'invite_accepted_at']);
        });
    }
};

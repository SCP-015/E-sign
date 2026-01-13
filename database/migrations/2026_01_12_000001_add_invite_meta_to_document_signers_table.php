<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->timestamp('invite_expires_at')->nullable()->after('invite_token');
            $table->timestamp('invite_accepted_at')->nullable()->after('invite_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('document_signers', function (Blueprint $table) {
            $table->dropColumn(['invite_expires_at', 'invite_accepted_at']);
        });
    }
};

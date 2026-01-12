<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInviteFieldsToDocumentSignersTable extends Migration
{
    public function up(): void
    {
        Schema::table("document_signers", function (Blueprint $table) {
            $table->string("email")->nullable()->after("name");
            $table->string("invite_token")->nullable()->unique()->after("email");
            $table->foreignId("user_id")->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table("document_signers", function (Blueprint $table) {
            $table->dropColumn(["email", "invite_token"]);
            $table->foreignId("user_id")->nullable(false)->change();
        });
    }
}
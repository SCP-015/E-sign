<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeSignatureIdNullableInSignaturePlacementsTable extends Migration
{
    public function up(): void
    {
        Schema::table("signature_placements", function (Blueprint $table) {
            $table->foreignId("signature_id")->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table("signature_placements", function (Blueprint $table) {
            $table->foreignId("signature_id")->nullable(false)->change();
        });
    }
}
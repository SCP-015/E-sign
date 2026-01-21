<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('signature_placements')) {
            return;
        }

        DB::statement('ALTER TABLE signature_placements DROP CONSTRAINT IF EXISTS signature_placements_signature_id_foreign');
    }

    public function down(): void
    {
        // no-op: signature is portable (central), so tenant DB must not enforce FK
    }
};

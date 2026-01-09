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
        Schema::table('documents', function (Blueprint $table) {
            // Remove legacy absolute coordinate columns (deprecated in v2)
            $table->dropColumn(['x_coord', 'y_coord']);
            
            // Remove legacy relative coordinate columns (replaced by signature image system)
            $table->dropColumn([
                'signature_x',
                'signature_y',
                'signature_width',
                'signature_height',
                'signature_page'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Restore legacy columns if rollback needed
            $table->integer('x_coord')->nullable();
            $table->integer('y_coord')->nullable();
            $table->decimal('signature_x', 5, 4)->nullable();
            $table->decimal('signature_y', 5, 4)->nullable();
            $table->decimal('signature_width', 5, 4)->nullable();
            $table->decimal('signature_height', 5, 4)->nullable();
            $table->integer('signature_page')->nullable();
        });
    }
};

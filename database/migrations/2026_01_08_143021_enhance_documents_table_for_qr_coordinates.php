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
            // File metadata
            $table->string('original_filename')->nullable()->after('file_path');
            $table->bigInteger('file_size')->nullable()->after('original_filename'); // bytes
            $table->string('mime_type')->nullable()->after('file_size');
            
            // QR/Signature position (relative coordinates 0-1)
            $table->decimal('signature_x', 5, 4)->nullable()->after('y_coord'); // 0.0000 - 1.0000
            $table->decimal('signature_y', 5, 4)->nullable()->after('signature_x');
            $table->decimal('signature_width', 5, 4)->nullable()->after('signature_y');
            $table->decimal('signature_height', 5, 4)->nullable()->after('signature_width');
            $table->integer('signature_page')->default(1)->after('signature_height');
            
            // Audit trail
            $table->timestamp('signed_at')->nullable()->after('status');
            
            // Keep old x_coord, y_coord for backward compatibility
            // They will be deprecated in favor of signature_x, signature_y
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'original_filename',
                'file_size',
                'mime_type',
                'signature_x',
                'signature_y',
                'signature_width',
                'signature_height',
                'signature_page',
                'signed_at',
            ]);
        });
    }
};

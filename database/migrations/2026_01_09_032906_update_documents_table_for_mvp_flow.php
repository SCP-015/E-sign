<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Add new fields for MVP flow
            $table->string('title')->nullable()->after('user_id');
            $table->string('file_type')->default('pdf')->after('file_path');
            $table->integer('file_size_bytes')->nullable()->after('file_type');
            $table->integer('page_count')->nullable()->after('file_size_bytes');
            $table->string('final_pdf_path')->nullable()->after('signed_path');
            $table->string('verify_token')->nullable()->unique()->after('final_pdf_path');
            $table->timestamp('completed_at')->nullable()->after('verify_token');
            
            // Update status enum to include new statuses
            // Keep column as string for broad DB compatibility.
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_status_check');
            DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_status_check CHECK (status IN ('DRAFT', 'IN_PROGRESS', 'COMPLETED', 'pending', 'signed'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_status_check');
        }

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'title',
                'file_type',
                'file_size_bytes',
                'page_count',
                'final_pdf_path',
                'verify_token',
                'completed_at'
            ]);
        });
    }
};

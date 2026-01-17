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
        Schema::table('tenants', function (Blueprint $table) {
            // Add organization details columns
            if (!Schema::hasColumn('tenants', 'name')) {
                $table->string('name')->after('id');
            }
            if (!Schema::hasColumn('tenants', 'code')) {
                $table->string('code', 20)->unique()->after('name');
            }
            if (!Schema::hasColumn('tenants', 'slug')) {
                $table->string('slug')->unique()->after('code');
            }
            if (!Schema::hasColumn('tenants', 'description')) {
                $table->text('description')->nullable()->after('slug');
            }
            if (!Schema::hasColumn('tenants', 'owner_id')) {
                $table->unsignedBigInteger('owner_id')->nullable()->after('description');
            }
            if (!Schema::hasColumn('tenants', 'plan')) {
                $table->string('plan')->default('free')->after('owner_id');
            }
        });

        // Add foreign key in separate statement to avoid issues
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'owner_id')) {
                // Check if foreign key doesn't already exist
                $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn(['name', 'code', 'slug', 'description', 'owner_id', 'plan']);
        });
    }
};

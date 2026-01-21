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
            if (!Schema::hasColumn('tenants', 'logo')) {
                $table->string('logo')->nullable()->after('name');
            }
            if (!Schema::hasColumn('tenants', 'banner')) {
                $table->string('banner')->nullable()->after('logo');
            }
            if (!Schema::hasColumn('tenants', 'website')) {
                $table->string('website')->nullable()->after('description');
            }
            if (!Schema::hasColumn('tenants', 'phone')) {
                $table->string('phone')->nullable()->after('website');
            }
            if (!Schema::hasColumn('tenants', 'address')) {
                $table->string('address')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('tenants', 'facebook')) {
                $table->string('facebook')->nullable()->after('address');
            }
            if (!Schema::hasColumn('tenants', 'twitter')) {
                $table->string('twitter')->nullable()->after('facebook');
            }
            if (!Schema::hasColumn('tenants', 'instagram')) {
                $table->string('instagram')->nullable()->after('twitter');
            }
            if (!Schema::hasColumn('tenants', 'linkedin')) {
                $table->string('linkedin')->nullable()->after('instagram');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $columns = ['logo', 'banner', 'website', 'phone', 'address', 'facebook', 'twitter', 'instagram', 'linkedin'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

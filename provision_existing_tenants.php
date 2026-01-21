<?php

/**
 * Script untuk provision database untuk tenant yang sudah ada
 * tapi belum punya database (created via UI sebelum TenantService fix)
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantDatabaseManager;
use App\Services\RootCAService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

$dbManager = app(TenantDatabaseManager::class);
$rootCAService = app(RootCAService::class);

echo "=== TENANT DATABASE PROVISIONING SCRIPT ===\n\n";

$tenants = Tenant::all();

foreach ($tenants as $tenant) {
    echo "Checking tenant: {$tenant->name} (ID: {$tenant->id})\n";
    
    // Check if database exists
    if ($dbManager->databaseExists($tenant->id)) {
        echo "  ✓ Database already exists\n";
        continue;
    }
    
    echo "  ⚠ Database NOT found. Creating...\n";
    
    try {
        // Create database
        $dbManager->createTenantDatabase($tenant);
        echo "  ✓ Database created\n";
        
        // Assign owner role
        $owner = User::find($tenant->owner_id);
        if ($owner) {
            $dbName = $dbManager->getTenantDatabaseName($tenant->id);
            config(['database.connections.tenant.database' => $dbName]);
            DB::purge('tenant');
            
            $ownerRole = DB::connection('tenant')->table('acl_roles')->where('name', 'owner')->first();
            
            if ($ownerRole) {
                DB::connection('tenant')->table('acl_model_has_roles')->insert([
                    'role_id' => $ownerRole->id,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $owner->id,
                ]);
                echo "  ✓ Owner role assigned\n";
            }
            
            DB::purge('tenant');
            DB::setDefaultConnection('pgsql');
        }
        
        // Generate Root CA if not exists
        if (!$tenant->has_root_ca) {
            $rootCAService->generateTenantRootCA($tenant);
            echo "  ✓ Root CA generated\n";
        }
        
        echo "  ✅ Tenant provisioned successfully!\n\n";
        
    } catch (\Exception $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n\n";
    }
}

echo "\n=== PROVISIONING COMPLETE ===\n";

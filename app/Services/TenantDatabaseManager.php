<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Exception;

class TenantDatabaseManager
{
    /**
     * Create database untuk tenant baru.
     *
     * @param Tenant $tenant
     * @return bool
     * @throws Exception
     */
    public function createTenantDatabase(Tenant $tenant): bool
    {
        try {
            $dbName = $this->getTenantDatabaseName($tenant->id);

            Log::info("Creating tenant database: {$dbName}");

            // Create database using central connection
            DB::connection('pgsql')->statement("CREATE DATABASE {$dbName}");

            // Grant privileges to database user
            $username = config('database.connections.pgsql.username');
            DB::connection('pgsql')->statement("GRANT ALL PRIVILEGES ON DATABASE {$dbName} TO {$username}");

            Log::info("Tenant database created successfully: {$dbName}");

            // Run tenant migrations
            $this->runTenantMigrations($tenant);

            // Create storage folders untuk tenant
            $this->createTenantStorageStructure($tenant);

            return true;
        } catch (Exception $e) {
            Log::error("Failed to create tenant database: " . $e->getMessage());
            
            // Cleanup: try to delete database if it was created
            try {
                $this->deleteTenantDatabase($tenant);
            } catch (Exception $cleanupError) {
                Log::error("Failed to cleanup after failed tenant DB creation: " . $cleanupError->getMessage());
            }
            
            throw new Exception("Failed to create tenant database: " . $e->getMessage());
        }
    }

    /**
     * Create storage folder structure untuk tenant.
     *
     * @param Tenant $tenant
     * @return void
     */
    protected function createTenantStorageStructure(Tenant $tenant): void
    {
        $basePath = storage_path("app/private/tenants/{$tenant->id}");
        
        $folders = [
            'documents',
            'signatures', 
            'certificates',
            'rootca',
            'secure',
        ];

        foreach ($folders as $folder) {
            $path = "{$basePath}/{$folder}";
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
                Log::info("Created tenant storage folder: {$path}");
            }
        }
    }

    /**
     * Run migrations untuk tenant database.
     *
     * @param Tenant $tenant
     * @return void
     */
    public function runTenantMigrations(Tenant $tenant): void
    {
        $dbName = $this->getTenantDatabaseName($tenant->id);

        Log::info("Running tenant migrations for database: {$dbName}");

        // Set tenant database connection
        config([
            'database.connections.tenant.database' => $dbName,
            'database.connections.tenant.driver' => 'pgsql',
        ]);

        // Purge connection to refresh config
        DB::purge('tenant');

        // Run migrations from database/migrations/tenant/
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        Log::info("Tenant migrations completed for: {$dbName}");

        // Run ACL seeder to populate roles & permissions
        $this->seedTenantAcl($tenant);

        // Purge connection untuk kembali ke central
        DB::purge('tenant');
        DB::setDefaultConnection('pgsql');
        
        Log::info("Tenant provisioning completed for: {$dbName}");
    }

    /**
     * Seed ACL data (roles & permissions) untuk tenant database.
     *
     * @param Tenant $tenant
     * @return void
     */
    protected function seedTenantAcl(Tenant $tenant): void
    {
        $dbName = $this->getTenantDatabaseName($tenant->id);
        
        Log::info("Seeding ACL data for tenant: {$dbName}");

        // Ensure connection is set to tenant
        config([
            'database.connections.tenant.database' => $dbName,
            'database.connections.tenant.driver' => 'pgsql',
        ]);
        DB::purge('tenant');

        // Run AclSeeder
        Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => 'Database\\Seeders\\AclSeeder',
            '--force' => true,
        ]);

        Log::info("ACL seeding completed for tenant: {$dbName}");
    }

    /**
     * Delete tenant database.
     *
     * @param Tenant $tenant
     * @return bool
     * @throws Exception
     */
    public function deleteTenantDatabase(Tenant $tenant): bool
    {
        try {
            $dbName = $this->getTenantDatabaseName($tenant->id);

            Log::info("Deleting tenant database: {$dbName}");

            // Terminate all connections to the database first
            DB::connection('pgsql')->statement("
                SELECT pg_terminate_backend(pg_stat_activity.pid)
                FROM pg_stat_activity
                WHERE pg_stat_activity.datname = '{$dbName}'
                  AND pid <> pg_backend_pid()
            ");

            // Drop database
            DB::connection('pgsql')->statement("DROP DATABASE IF EXISTS {$dbName}");

            Log::info("Tenant database deleted successfully: {$dbName}");

            return true;
        } catch (Exception $e) {
            Log::error("Failed to delete tenant database: " . $e->getMessage());
            throw new Exception("Failed to delete tenant database: " . $e->getMessage());
        }
    }

    /**
     * Get tenant database name dari tenant ID.
     *
     * Format: tenant_{ulid_lowercase}
     * Contoh: tenant_01jkabcd1234567890abcdefgh
     *
     * @param string $tenantId
     * @return string
     */
    public function getTenantDatabaseName(string|\App\Models\Tenant $tenantOrId): string
    {
        if ($tenantOrId instanceof \App\Models\Tenant) {
            if (!empty($tenantOrId->tenancy_db_name)) {
                return $tenantOrId->tenancy_db_name;
            }
            $tenantId = $tenantOrId->id;
        } else {
            $tenantId = $tenantOrId;
            // Try to find tenant to get tenancy_db_name
            $tenant = \App\Models\Tenant::find($tenantId);
            if ($tenant && !empty($tenant->tenancy_db_name)) {
                return $tenant->tenancy_db_name;
            }
        }

        // PostgreSQL prefers lowercase database names
        $cleanId = str_replace('-', '_', strtolower($tenantId));
        $prefix = config('tenancy.database.prefix', 'tenant_');
        return "{$prefix}{$cleanId}";
    }

    /**
     * Switch active connection ke tenant database.
     *
     * @param string $tenantId
     * @return void
     */
    public function switchToTenantDatabase(string $tenantId): void
    {
        $dbName = $this->getTenantDatabaseName($tenantId);

        // Set tenant database name
        config([
            'database.connections.tenant.database' => $dbName,
            'database.connections.tenant.driver' => 'pgsql',
        ]);

        // Purge existing connection to apply new config
        DB::purge('tenant');

        // Set default connection to tenant
        DB::setDefaultConnection('tenant');

        Log::debug("Switched to tenant database: {$dbName}");
    }

    /**
     * Switch back ke central database.
     *
     * @return void
     */
    public function switchToCentralDatabase(): void
    {
        DB::setDefaultConnection('pgsql');
        Log::debug("Switched back to central database");
    }

    /**
     * Check apakah tenant database exists.
     *
     * @param string $tenantId
     * @return bool
     */
    public function databaseExists(string $tenantId): bool
    {
        $dbName = $this->getTenantDatabaseName($tenantId);

        $result = DB::connection('pgsql')->select(
            "SELECT 1 FROM pg_database WHERE datname = ?",
            [$dbName]
        );

        return count($result) > 0;
    }

    /**
     * Get database size untuk tenant.
     *
     * @param string $tenantId
     * @return int Size in bytes
     */
    public function getDatabaseSize(string $tenantId): int
    {
        $dbName = $this->getTenantDatabaseName($tenantId);

        $result = DB::connection('pgsql')->selectOne(
            "SELECT pg_database_size(?) as size",
            [$dbName]
        );

        return $result->size ?? 0;
    }

    /**
     * Backup tenant database to SQL file.
     *
     * @param Tenant $tenant
     * @param string $backupPath
     * @return bool
     */
    public function backupDatabase(Tenant $tenant, string $backupPath): bool
    {
        $dbName = $this->getTenantDatabaseName($tenant->id);
        $host = config('database.connections.pgsql.host');
        $port = config('database.connections.pgsql.port');
        $username = config('database.connections.pgsql.username');

        // Use pg_dump to create backup
        $command = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -F c -b -v -f %s %s',
            escapeshellarg(config('database.connections.pgsql.password')),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($backupPath),
            escapeshellarg($dbName)
        );

        exec($command, $output, $returnCode);

        return $returnCode === 0;
    }
}

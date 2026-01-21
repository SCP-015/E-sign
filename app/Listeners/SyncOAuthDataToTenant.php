<?php

namespace App\Listeners;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Illuminate\Support\Facades\Event;

class SyncOAuthDataToTenant
{
    protected $tenant;

    public function __construct($tenant = null)
    {
        $this->tenant = $tenant;
    }

    /**
     * Handle the event.
     * 
     * Saat digunakan dalam JobPipeline Stancl\Tenancy\Events\TenantCreated,
     * yang dikirimkan adalah objek Tenant.
     */
    public function handle($tenant = null)
    {
        $tenant = $tenant ?? $this->tenant;

        if (!$tenant instanceof Tenant) {
            // Jika dipanggil dari event listener biasa, mungkin dibungkus event
            if (isset($tenant->tenant)) {
                $tenant = $tenant->tenant;
            } else {
                return;
            }
        }

        Log::info("Syncing OAuth data to tenant: {$tenant->id}");

        // Kita ingin menjalankan ini SETELAH migrasi selesai.
        // Jika kita berada di JobPipeline setelah Jobs\MigrateDatabase, 
        // maka migrasi sudah selesai untuk tenant ini.
        
        $this->performSync($tenant);
    }

    protected function performSync(Tenant $tenant)
    {
        $connection = config('database.default'); // usually 'pgsql'

        try {
            // Fetch OAuth data from central database
            $oauthClients = DB::connection($connection)->table('oauth_clients')->get();
            $oauthPACs = DB::connection($connection)->table('oauth_personal_access_clients')->get();
            $oauthAccessTokens = DB::connection($connection)->table('oauth_access_tokens')->get();

            Log::info("OAuth data fetched from central", [
                'clients' => $oauthClients->count(),
                'pacs' => $oauthPACs->count(),
            ]);

            // Sync to tenant
            $dbManager = app(\App\Services\TenantDatabaseManager::class);
            $dbManager->switchToTenantDatabase($tenant->id);

            try {
                foreach ($oauthClients as $client) {
                    DB::table('oauth_clients')->insertOrIgnore((array) $client);
                }

                foreach ($oauthPACs as $pac) {
                    DB::table('oauth_personal_access_clients')->insertOrIgnore((array) $pac);
                }

                foreach ($oauthAccessTokens as $token) {
                    DB::table('oauth_access_tokens')->insertOrIgnore((array) $token);
                }
            } finally {
                $dbManager->switchToCentralDatabase();
            }

            Log::info("OAuth data synced successfully to tenant: {$tenant->id}");
        } catch (\Exception $e) {
            Log::error("Failed to sync OAuth data to tenant: " . $e->getMessage());
        }
    }
}

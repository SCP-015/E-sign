<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\TenantDatabaseManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnsureTenantDatabaseExists implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle()
    {
        Log::info("EnsureTenantDatabaseExists: Checking database for tenant {$this->tenant->id}");
        
        $dbManager = app(TenantDatabaseManager::class);
        
        if (!$dbManager->databaseExists($this->tenant->id)) {
            Log::info("EnsureTenantDatabaseExists: Creating database for tenant {$this->tenant->id}");
            $dbManager->createTenantDatabase($this->tenant);
        } else {
            Log::info("EnsureTenantDatabaseExists: Database already exists for tenant {$this->tenant->id}");

            // Ensure schema is up to date (idempotent). This prevents missing tables like `documents`.
            $dbManager->runTenantMigrations($this->tenant);
        }
    }
}

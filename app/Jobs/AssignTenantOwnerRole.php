<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignTenantOwnerRole implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle()
    {
        $ownerId = $this->tenant->owner_id;
        Log::info("Assigning owner role to user {$ownerId} in tenant: {$this->tenant->id}");

        $dbManager = app(\App\Services\TenantDatabaseManager::class);
        $dbManager->switchToTenantDatabase($this->tenant->id);

        try {
            $ownerRole = DB::table('acl_roles')
                ->where('name', 'owner')
                ->first();

            if ($ownerRole) {
                DB::table('acl_model_has_roles')->insertOrIgnore([
                    'role_id' => $ownerRole->id,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $ownerId,
                ]);
                Log::info("Owner role assigned successfully.");
            } else {
                Log::error("Owner role not found in tenant DB.");
            }
        } finally {
            $dbManager->switchToCentralDatabase();
        }
    }
}

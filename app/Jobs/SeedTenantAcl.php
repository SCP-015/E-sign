<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SeedTenantAcl implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle()
    {
        Log::info("Seeding ACL for tenant: {$this->tenant->id}");

        $this->tenant->run(function () {
            Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\AclSeeder',
                '--force' => true,
            ]);
        });

        Log::info("ACL seeded for tenant: {$this->tenant->id}");
    }
}

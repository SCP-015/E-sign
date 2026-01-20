<?php

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateTenantStorage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle()
    {
        Log::info("Creating storage for tenant: {$this->tenant->id}");

        $basePath = storage_path("app/private/tenants/{$this->tenant->id}");
        
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
}

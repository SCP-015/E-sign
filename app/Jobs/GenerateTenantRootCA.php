<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\RootCAService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateTenantRootCA implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tenant;

    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function handle(RootCAService $rootCAService)
    {
        Log::info("Generating Root CA for tenant: {$this->tenant->id}");

        try {
            $rootCAService->generateTenantRootCA($this->tenant);
            Log::info("Root CA generated successfully.");
        } catch (\Exception $e) {
            Log::error("Failed to generate Root CA: " . $e->getMessage());
        }
    }
}

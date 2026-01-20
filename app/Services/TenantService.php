<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Tenant\User as TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class TenantService
{
    protected TenantDatabaseManager $dbManager;
    protected RootCAService $rootCAService;

    public function __construct(
        TenantDatabaseManager $dbManager,
        RootCAService $rootCAService
    ) {
        $this->dbManager = $dbManager;
        $this->rootCAService = $rootCAService;
    }
    /**
     * Create a new tenant/organization.
     *
     * @param array $data
     * @param User $owner
     * @return Tenant
     * @throws Exception
     */
    public function store(array $data, User $owner): Tenant
    {
        // Check if user can create more tenants (max 5)
        if (!$owner->canCreateTenant()) {
            throw new Exception('Anda sudah mencapai batas maksimal 5 organization.');
        }

        // Step 1: Create tenant record
        // Events in Stancl\Tenancy (TenantCreated) will handle DB creation, migrations, and infrastructure
        // NOTE: No transaction here because PG doesn't allow CREATE DATABASE inside transaction
        // if the creation jobs are synchronous.
        $tenant = Tenant::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'owner_id' => $owner->id,
            'plan' => $data['plan'] ?? 'free',
            // Company/Organization DN fields
            'company_legal_name' => $data['company_legal_name'] ?? null,
            'company_country' => $data['company_country'] ?? 'ID',
            'company_state' => $data['company_state'] ?? null,
            'company_city' => $data['company_city'] ?? null,
            'company_address' => $data['company_address'] ?? null,
            'company_postal_code' => $data['company_postal_code'] ?? null,
            'company_organization_unit' => $data['company_organization_unit'] ?? null,
        ]);

        Log::info("Tenant created. Automated provisioning started via events for ID: {$tenant->id}");

        // Add owner as tenant user (in central DB)
        TenantUser::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'is_owner' => true,
            'joined_at' => now(),
        ]);

        return $tenant;
    }

    /**
     * Update a tenant.
     *
     * @param array $data
     * @param Tenant $tenant
     * @return Tenant
     */
    public function update(array $data, Tenant $tenant): Tenant
    {
        $tenant->update([
            'name' => $data['name'] ?? $tenant->name,
            'description' => $data['description'] ?? $tenant->description,
            'plan' => $data['plan'] ?? $tenant->plan,
        ]);

        // Update slug if name changed
        if (isset($data['name']) && $data['name'] !== $tenant->getOriginal('name')) {
            $tenant->slug = Tenant::generateSlug($data['name']);
            $tenant->save();
        }

        return $tenant->fresh();
    }

    /**
     * Delete a tenant.
     * Only the owner can delete.
     *
     * @param Tenant $tenant
     * @param User $user
     * @return bool
     * @throws Exception
     */
    public function destroy(Tenant $tenant, User $user): bool
    {
        // Only owner can delete
        if (!$tenant->isOwner($user)) {
            throw new Exception('Hanya pemilik yang dapat menghapus organization.');
        }

        return DB::transaction(function () use ($tenant) {
            // Delete all tenant users
            $tenant->tenantUsers()->delete();
            
            // Delete all invitations
            $tenant->invitations()->delete();
            
            // Delete tenant
            return $tenant->delete();
        });
    }

    /**
     * Get all tenants for a user.
     *
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserTenants(User $user)
    {
        return $user->tenants()->orderBy('name')->get();
    }
}

<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class TenantService
{
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

        return DB::transaction(function () use ($data, $owner) {
            // Create tenant
            $tenant = Tenant::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'owner_id' => $owner->id,
                'plan' => $data['plan'] ?? 'free',
            ]);

            // Add owner as owner
            TenantUser::create([
                'tenant_id' => $tenant->id,
                'user_id' => $owner->id,
                'role' => 'owner',
                'is_owner' => true,
                'joined_at' => now(),
            ]);

            return $tenant;
        });
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

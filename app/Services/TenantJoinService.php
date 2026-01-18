<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class TenantJoinService
{
    /**
     * Join a tenant using invitation code.
     *
     * @param string $code
     * @param User $user
     * @return Tenant
     * @throws Exception
     */
    public function joinByCode(string $code, User $user): Tenant
    {
        // First, try to find by invitation code
        $invitation = TenantInvitation::where('code', $code)->first();
        
        if ($invitation) {
            return $this->joinByInvitation($invitation, $user);
        }

        // If not found, try tenant's direct code
        $tenant = Tenant::where('code', $code)->first();
        
        if (!$tenant) {
            throw new Exception('Kode tidak valid atau sudah tidak berlaku.');
        }

        // Join with default role 'user'
        return $this->attachUserToTenant($user, $tenant, 'user');
    }

    /**
     * Join a tenant using invitation.
     *
     * @param TenantInvitation $invitation
     * @param User $user
     * @return Tenant
     * @throws Exception
     */
    protected function joinByInvitation(TenantInvitation $invitation, User $user): Tenant
    {
        // Check if invitation is valid
        if (!$invitation->isValid()) {
            throw new Exception('Kode undangan sudah tidak berlaku atau sudah mencapai batas penggunaan.');
        }

        $tenant = $invitation->tenant;

        // Check if user already member
        $existingMember = TenantUser::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingMember) {
            throw new Exception('Anda sudah menjadi anggota organization ini.');
        }

        return DB::transaction(function () use ($invitation, $user, $tenant) {
            // Attach user with invitation's role
            $this->attachUserToTenant($user, $tenant, $invitation->role);
            
            // Increment invitation usage
            $invitation->incrementUse();

            return $tenant;
        });
    }

    /**
     * Attach user to tenant with specified role.
     *
     * @param User $user
     * @param Tenant $tenant
     * @param string $role
     * @return Tenant
     * @throws Exception
     */
    public function attachUserToTenant(User $user, Tenant $tenant, string $role = 'user'): Tenant
    {
        // Check if already member
        $existingMember = TenantUser::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingMember) {
            throw new Exception('User sudah menjadi anggota organization ini.');
        }

        TenantUser::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_owner' => false,
            'joined_at' => now(),
        ]);

        // Sync role to ACL so permission checks work in tenant context
        $user->assignRoleInTenant($role, $tenant->id);

        return $tenant;
    }
}

<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use Carbon\Carbon;

class TenantInvitationService
{
    /**
     * Default expiry days for invitation.
     */
    protected const DEFAULT_EXPIRY_DAYS = 7;

    /**
     * Create a new invitation.
     *
     * @param Tenant $tenant
     * @param array $data
     * @param User $creator
     * @return TenantInvitation
     */
    public function create(Tenant $tenant, array $data, User $creator): TenantInvitation
    {
        $expiryDays = $data['expiry_days'] ?? self::DEFAULT_EXPIRY_DAYS;

        return TenantInvitation::create([
            'tenant_id' => $tenant->id,
            'role' => $data['role'] ?? 'member',
            'expires_at' => Carbon::now()->addDays($expiryDays),
            'max_uses' => $data['max_uses'] ?? null,
            'created_by' => $creator->id,
        ]);
    }

    /**
     * Get all invitations for a tenant.
     *
     * @param Tenant $tenant
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByTenant(Tenant $tenant)
    {
        return $tenant->invitations()
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Delete an invitation.
     *
     * @param TenantInvitation $invitation
     * @return bool
     */
    public function delete(TenantInvitation $invitation): bool
    {
        return $invitation->delete();
    }

    /**
     * Validate and use an invitation code.
     *
     * @param string $code
     * @param User $user
     * @return TenantInvitation|null
     */
    public function validateCode(string $code): ?TenantInvitation
    {
        $invitation = TenantInvitation::where('code', $code)->first();

        if (!$invitation || !$invitation->isValid()) {
            return null;
        }

        return $invitation;
    }
}

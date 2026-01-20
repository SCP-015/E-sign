<?php

namespace App\Services\Tenant;

use App\Models\Tenant;
use App\Models\Tenant\Invitation as TenantInvitation;
use App\Models\User;

class InvitationService
{
    public function __construct(private readonly TenantInvitationService $tenantInvitationService)
    {
    }

    public function listInvitations(User $actor, Tenant $organization): array
    {
        $membership = $organization->tenantUsers()
            ->where('user_id', $actor->id)
            ->whereIn('role', ['owner', 'admin'])
            ->first();

        if (!$membership) {
            return [
                'status' => 'error',
                'message' => 'Only owner or admin can view invitations.',
                'code' => 403,
                'data' => null,
            ];
        }

        $invitations = $this->tenantInvitationService
            ->getByTenant($organization)
            ->loadMissing('createdBy');

        return [
            'status' => 'success',
            'message' => 'OK',
            'code' => 200,
            'data' => $invitations,
        ];
    }

    public function createInvitation(User $actor, Tenant $organization, array $payload): array
    {
        $membership = $organization->tenantUsers()
            ->where('user_id', $actor->id)
            ->whereIn('role', ['owner', 'admin'])
            ->first();

        if (!$membership) {
            return [
                'status' => 'error',
                'message' => 'Only owner or admin can create invitations.',
                'code' => 403,
                'data' => null,
            ];
        }

        $invitation = $this->tenantInvitationService->create($organization, $payload, $actor);
        $invitation->loadMissing('createdBy');

        return [
            'status' => 'success',
            'message' => 'Invitation created successfully.',
            'code' => 201,
            'data' => $invitation,
        ];
    }

    public function deleteInvitation(User $actor, Tenant $organization, TenantInvitation $invitation): array
    {
        $membership = $organization->tenantUsers()
            ->where('user_id', $actor->id)
            ->whereIn('role', ['owner', 'admin'])
            ->first();

        if (!$membership) {
            return [
                'status' => 'error',
                'message' => 'Only owner or admin can delete invitations.',
                'code' => 403,
                'data' => null,
            ];
        }

        if ((string) $invitation->tenant_id !== (string) $organization->id) {
            return [
                'status' => 'error',
                'message' => 'Invitation not found in this organization.',
                'code' => 404,
                'data' => null,
            ];
        }

        $this->tenantInvitationService->delete($invitation);

        return [
            'status' => 'success',
            'message' => 'Invitation deleted successfully.',
            'code' => 200,
            'data' => null,
        ];
    }
}

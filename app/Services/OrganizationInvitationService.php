<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;

class OrganizationInvitationService
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
                'message' => 'Hanya owner atau admin yang dapat melihat undangan.',
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
                'message' => 'Hanya owner atau admin yang dapat membuat undangan.',
                'code' => 403,
                'data' => null,
            ];
        }

        $invitation = $this->tenantInvitationService->create($organization, $payload, $actor);
        $invitation->loadMissing('createdBy');

        return [
            'status' => 'success',
            'message' => 'Undangan berhasil dibuat.',
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
                'message' => 'Hanya owner atau admin yang dapat menghapus undangan.',
                'code' => 403,
                'data' => null,
            ];
        }

        if ((string) $invitation->tenant_id !== (string) $organization->id) {
            return [
                'status' => 'error',
                'message' => 'Undangan tidak ditemukan di organization ini.',
                'code' => 404,
                'data' => null,
            ];
        }

        $this->tenantInvitationService->delete($invitation);

        return [
            'status' => 'success',
            'message' => 'Undangan berhasil dihapus.',
            'code' => 200,
            'data' => null,
        ];
    }
}

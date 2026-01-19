<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

class OrganizationMemberService
{
    public function listMembers(User $actor, Tenant $organization): array
    {
        $membership = $organization->tenantUsers()->where('user_id', $actor->id)->first();
        if (!$membership) {
            return [
                'status' => 'error',
                'message' => 'Anda bukan anggota organization ini.',
                'code' => 403,
                'data' => null,
            ];
        }

        $members = $organization->tenantUsers()
            ->with('user:id,name,email,avatar')
            ->orderByDesc('is_owner')
            ->orderBy('role')
            ->get();

        return [
            'status' => 'success',
            'message' => 'OK',
            'code' => 200,
            'data' => $members,
        ];
    }

    public function updateMemberRole(User $actor, Tenant $organization, TenantUser $member, string $role): array
    {
        $adminMembership = $organization->tenantUsers()
            ->where('user_id', $actor->id)
            ->whereIn('role', ['owner', 'admin'])
            ->first();

        if (!$adminMembership) {
            return [
                'status' => 'error',
                'message' => 'Hanya owner atau admin yang dapat mengubah role member.',
                'code' => 403,
                'data' => null,
            ];
        }

        if ((bool) $member->is_owner) {
            return [
                'status' => 'error',
                'message' => 'Role pemilik organization tidak dapat diubah.',
                'code' => 422,
                'data' => null,
            ];
        }

        if ((string) $member->tenant_id !== (string) $organization->id) {
            return [
                'status' => 'error',
                'message' => 'Member tidak ditemukan di organization ini.',
                'code' => 404,
                'data' => null,
            ];
        }

        $member->update([
            'role' => $role,
        ]);

        $targetUser = User::find($member->user_id);
        if ($targetUser) {
            $targetUser->assignRoleInTenant($member->role, $organization->id);
        }

        return [
            'status' => 'success',
            'message' => 'Role member berhasil diubah.',
            'code' => 200,
            'data' => $member,
        ];
    }

    public function removeMember(User $actor, Tenant $organization, TenantUser $member): array
    {
        $adminMembership = $organization->tenantUsers()
            ->where('user_id', $actor->id)
            ->whereIn('role', ['owner', 'admin'])
            ->first();

        if (!$adminMembership) {
            return [
                'status' => 'error',
                'message' => 'Hanya owner atau admin yang dapat menghapus member.',
                'code' => 403,
                'data' => null,
            ];
        }

        if ((bool) $member->is_owner) {
            return [
                'status' => 'error',
                'message' => 'Pemilik organization tidak dapat dihapus.',
                'code' => 422,
                'data' => null,
            ];
        }

        if ((string) $member->tenant_id !== (string) $organization->id) {
            return [
                'status' => 'error',
                'message' => 'Member tidak ditemukan di organization ini.',
                'code' => 404,
                'data' => null,
            ];
        }

        if ((int) $member->user_id === (int) $actor->id) {
            return [
                'status' => 'error',
                'message' => 'Anda tidak dapat menghapus diri sendiri.',
                'code' => 422,
                'data' => null,
            ];
        }

        $member->delete();

        return [
            'status' => 'success',
            'message' => 'Member berhasil dihapus dari organization.',
            'code' => 200,
            'data' => null,
        ];
    }
}

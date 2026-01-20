<?php

namespace App\Services\Tenant;

use App\Models\Tenant;
use App\Models\Tenant\User as TenantUser;
use App\Models\User;

class MemberService
{
    public function listMembers(User $actor, Tenant $organization): array
    {
        $membership = $organization->tenantUsers()->where('user_id', $actor->id)->first();
        if (!$membership) {
            return [
                'status' => 'error',
                'message' => 'You are not a member of this organization.',
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
                'message' => 'Only owner or admin can change member roles.',
                'code' => 403,
                'data' => null,
            ];
        }

        if ((bool) $member->is_owner) {
            return [
                'status' => 'error',
                'message' => 'Organization owner role cannot be changed.',
                'code' => 422,
                'data' => null,
            ];
        }

        if ((string) $member->tenant_id !== (string) $organization->id) {
            return [
                'status' => 'error',
                'message' => 'Member not found in this organization.',
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
            'message' => 'Member role updated successfully.',
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
                'message' => 'Only owner or admin can remove members.',
                'code' => 403,
                'data' => null,
            ];
        }

        if ((bool) $member->is_owner) {
            return [
                'status' => 'error',
                'message' => 'Organization owner cannot be removed.',
                'code' => 422,
                'data' => null,
            ];
        }

        if ((string) $member->tenant_id !== (string) $organization->id) {
            return [
                'status' => 'error',
                'message' => 'Member not found in this organization.',
                'code' => 404,
                'data' => null,
            ];
        }

        if ($member->user_id === $actor->id) {
            return [
                'status' => 'error',
                'message' => 'You cannot remove yourself.',
                'code' => 422,
                'data' => null,
            ];
        }

        $member->delete();

        return [
            'status' => 'success',
            'message' => 'Member removed successfully.',
            'code' => 200,
            'data' => null,
        ];
    }
}

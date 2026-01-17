<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrganizationMemberController extends Controller
{
    /**
     * Get list of members in an organization.
     */
    public function index(Tenant $organization): JsonResponse
    {
        $user = Auth::user();

        // Check if user is member
        $membership = $organization->tenantUsers()->where('user_id', $user->id)->first();
        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'Anda bukan anggota organization ini.',
            ], 403);
        }

        $members = $organization->tenantUsers()
            ->with('user:id,name,email,avatar')
            ->orderByDesc('is_owner')
            ->orderBy('role')
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'user_id' => $member->user_id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                    'avatar' => $member->user->avatar,
                    'role' => $member->role,
                    'is_owner' => $member->is_owner,
                    'joined_at' => $member->joined_at?->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $members,
        ]);
    }

    /**
     * Update member role.
     */
    public function update(Request $request, Tenant $organization, TenantUser $member): JsonResponse
    {
        $user = Auth::user();

        // Check if user is admin
        $adminMembership = $organization->tenantUsers()
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->first();

        if (!$adminMembership) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya admin yang dapat mengubah role member.',
            ], 403);
        }

        // Can't change owner role
        if ($member->is_owner) {
            return response()->json([
                'success' => false,
                'message' => 'Role pemilik organization tidak dapat diubah.',
            ], 422);
        }

        // Validate member belongs to organization
        if ($member->tenant_id !== $organization->id) {
            return response()->json([
                'success' => false,
                'message' => 'Member tidak ditemukan di organization ini.',
            ], 404);
        }

        $request->validate([
            'role' => 'required|in:admin,manager,user',
        ]);

        $member->update([
            'role' => $request->input('role'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role member berhasil diubah.',
            'data' => [
                'id' => $member->id,
                'role' => $member->role,
            ],
        ]);
    }

    /**
     * Remove member from organization.
     */
    public function destroy(Tenant $organization, TenantUser $member): JsonResponse
    {
        $user = Auth::user();

        // Check if user is admin
        $adminMembership = $organization->tenantUsers()
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->first();

        if (!$adminMembership) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya admin yang dapat menghapus member.',
            ], 403);
        }

        // Can't remove owner
        if ($member->is_owner) {
            return response()->json([
                'success' => false,
                'message' => 'Pemilik organization tidak dapat dihapus.',
            ], 422);
        }

        // Validate member belongs to organization
        if ($member->tenant_id !== $organization->id) {
            return response()->json([
                'success' => false,
                'message' => 'Member tidak ditemukan di organization ini.',
            ], 404);
        }

        // Can't remove yourself
        if ($member->user_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menghapus diri sendiri.',
            ], 422);
        }

        $member->delete();

        return response()->json([
            'success' => true,
            'message' => 'Member berhasil dihapus dari organization.',
        ]);
    }
}

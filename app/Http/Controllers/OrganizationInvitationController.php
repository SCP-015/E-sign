<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Services\TenantInvitationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrganizationInvitationController extends Controller
{
    protected TenantInvitationService $invitationService;

    public function __construct(TenantInvitationService $invitationService)
    {
        $this->invitationService = $invitationService;
    }

    /**
     * Get list of invitations for an organization.
     */
    public function index(Tenant $organization): JsonResponse
    {
        $user = Auth::user();

        // Check if user is owner, admin or manager
        $membership = $organization->tenantUsers()
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'admin', 'manager'])
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya owner, admin atau manager yang dapat melihat undangan.',
            ], 403);
        }

        $invitations = $this->invitationService->getByTenant($organization);

        return response()->json([
            'success' => true,
            'data' => $invitations->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'code' => $invitation->code,
                    'role' => $invitation->role,
                    'expires_at' => $invitation->expires_at?->toISOString(),
                    'is_expired' => $invitation->expires_at?->isPast() ?? false,
                    'max_uses' => $invitation->max_uses,
                    'used_count' => $invitation->used_count,
                    'is_valid' => $invitation->isValid(),
                    'created_by' => [
                        'id' => $invitation->createdBy->id,
                        'name' => $invitation->createdBy->name,
                    ],
                    'created_at' => $invitation->created_at?->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * Create a new invitation.
     */
    public function store(Request $request, Tenant $organization): JsonResponse
    {
        $user = Auth::user();

        // Check if user is owner, admin or manager
        $membership = $organization->tenantUsers()
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'admin', 'manager'])
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya owner, admin atau manager yang dapat membuat undangan.',
            ], 403);
        }

        $request->validate([
            'role' => 'required|in:admin,manager,user',
            'expiry_days' => 'nullable|integer|min:1|max:30',
            'max_uses' => 'nullable|integer|min:1',
        ]);

        $invitation = $this->invitationService->create(
            $organization,
            $request->only(['role', 'expiry_days', 'max_uses']),
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Undangan berhasil dibuat.',
            'data' => [
                'id' => $invitation->id,
                'code' => $invitation->code,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at?->toISOString(),
                'max_uses' => $invitation->max_uses,
            ],
        ], 201);
    }

    /**
     * Delete an invitation.
     */
    public function destroy(Tenant $organization, TenantInvitation $invitation): JsonResponse
    {
        $user = Auth::user();

        // Check if user is owner or admin
        $membership = $organization->tenantUsers()
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'admin'])
            ->first();

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya owner atau admin yang dapat menghapus undangan.',
            ], 403);
        }

        // Validate invitation belongs to organization
        if ($invitation->tenant_id !== $organization->id) {
            return response()->json([
                'success' => false,
                'message' => 'Undangan tidak ditemukan di organization ini.',
            ], 404);
        }

        $this->invitationService->delete($invitation);

        return response()->json([
            'success' => true,
            'message' => 'Undangan berhasil dihapus.',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\StoreOrganizationInvitationRequest;
use App\Http\Resources\OrganizationInvitationResource;
use App\Models\Tenant;
use App\Models\Tenant\Invitation as TenantInvitation;
use App\Services\Tenant\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class InvitationController extends Controller
{
    protected InvitationService $invitationService;

    public function __construct(InvitationService $invitationService)
    {
        $this->invitationService = $invitationService;
    }

    /**
     * Get list of invitations for an organization.
     */
    public function index(Tenant $organization): JsonResponse
    {
        $user = Auth::user();

        $result = $this->invitationService->listInvitations($user, $organization);
        if (($result['status'] ?? 'success') !== 'success') {
            return ApiResponse::fromService($result);
        }

        $invitations = $result['data'] ?? collect();
        $resource = OrganizationInvitationResource::collection($invitations);
        return ApiResponse::success($resource->toArray(request()));
    }

    /**
     * Create a new invitation.
     */
    public function store(StoreOrganizationInvitationRequest $request, Tenant $organization): JsonResponse
    {
        $user = Auth::user();

        $result = $this->invitationService->createInvitation(
            $user,
            $organization,
            $request->validated()
        );

        if (($result['status'] ?? 'success') !== 'success') {
            return ApiResponse::fromService($result);
        }

        $invitation = $result['data'];
        return ApiResponse::success(
            new OrganizationInvitationResource($invitation),
            $result['message'] ?? 'OK',
            $result['code'] ?? 201
        );
    }

    /**
     * Delete an invitation.
     */
    public function destroy(Tenant $organization, TenantInvitation $invitation): JsonResponse
    {
        $user = Auth::user();

        $result = $this->invitationService->deleteInvitation($user, $organization, $invitation);
        return ApiResponse::fromService($result);
    }
}

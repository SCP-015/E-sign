<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\StoreOrganizationInvitationRequest;
use App\Http\Resources\OrganizationInvitationResource;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Services\OrganizationInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrganizationInvitationController extends Controller
{
    protected OrganizationInvitationService $organizationInvitationService;

    public function __construct(OrganizationInvitationService $organizationInvitationService)
    {
        $this->organizationInvitationService = $organizationInvitationService;
    }

    /**
     * Get list of invitations for an organization.
     */
    public function index(Tenant $organization): JsonResponse
    {
        $user = Auth::user();

        $result = $this->organizationInvitationService->listInvitations($user, $organization);
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

        $result = $this->organizationInvitationService->createInvitation(
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

        $result = $this->organizationInvitationService->deleteInvitation($user, $organization, $invitation);
        return ApiResponse::fromService($result);
    }
}

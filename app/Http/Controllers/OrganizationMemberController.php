<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\UpdateOrganizationMemberRoleRequest;
use App\Http\Resources\OrganizationMemberResource;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\OrganizationMemberService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrganizationMemberController extends Controller
{
    protected OrganizationMemberService $organizationMemberService;

    public function __construct(OrganizationMemberService $organizationMemberService)
    {
        $this->organizationMemberService = $organizationMemberService;
    }

    /**
     * Get list of members in an organization.
     */
    public function index(Tenant $organization): JsonResponse
    {
        $user = Auth::user();
        $result = $this->organizationMemberService->listMembers($user, $organization);

        if (($result['status'] ?? 'success') !== 'success') {
            return ApiResponse::fromService($result);
        }

        $members = $result['data'] ?? collect();
        $resource = OrganizationMemberResource::collection($members);
        return ApiResponse::success($resource->toArray(request()));
    }

    /**
     * Update member role.
     */
    public function update(UpdateOrganizationMemberRoleRequest $request, Tenant $organization, TenantUser $member): JsonResponse
    {
        $user = Auth::user();
        $result = $this->organizationMemberService->updateMemberRole(
            $user,
            $organization,
            $member,
            (string) $request->validated()['role']
        );

        if (($result['status'] ?? 'success') !== 'success') {
            return ApiResponse::fromService($result);
        }

        $updatedMember = $result['data'];
        $updatedMember->loadMissing('user:id,name,email,avatar');

        return ApiResponse::success(
            new OrganizationMemberResource($updatedMember),
            $result['message'] ?? 'OK'
        );
    }

    /**
     * Remove member from organization.
     */
    public function destroy(Tenant $organization, TenantUser $member): JsonResponse
    {
        $user = Auth::user();
        $result = $this->organizationMemberService->removeMember($user, $organization, $member);

        return ApiResponse::fromService($result);
    }
}

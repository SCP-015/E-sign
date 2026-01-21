<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Http\Requests\UpdateOrganizationMemberRoleRequest;
use App\Http\Resources\OrganizationMemberResource;
use App\Models\Tenant;
use App\Models\Tenant\User as TenantUser;
use App\Services\Tenant\MemberService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MemberController extends Controller
{
    protected MemberService $memberService;

    public function __construct(MemberService $memberService)
    {
        $this->memberService = $memberService;
    }

    /**
     * Get list of members in an organization.
     */
    public function index(Tenant $organization): JsonResponse
    {
        $user = Auth::user();
        $result = $this->memberService->listMembers($user, $organization);

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
        $result = $this->memberService->updateMemberRole(
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
        $result = $this->memberService->removeMember($user, $organization, $member);

        return ApiResponse::fromService($result);
    }
}

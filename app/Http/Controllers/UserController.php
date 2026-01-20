<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService)
    {
    }

    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        return ApiResponse::fromService($this->userService->profile($user->id));
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        $user->load(['signatures', 'certificate', 'tenants']);

        $tenantId = session('current_tenant_id') ?? $user->current_tenant_id;
        $currentTenant = null;
        $role = null;
        $permissions = [];

        if ($tenantId) {
            $currentTenant = $user->tenants()->where('tenants.id', $tenantId)->first();
            if ($currentTenant) {
                $aclRole = $user->getRoleInTenant($currentTenant->id);
                $role = $aclRole ? $aclRole->name : ($currentTenant->pivot->role ?? 'member');
            }
            $permissions = $user->getPermissionsInTenant($tenantId);
        }

        return ApiResponse::success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar,
            'kycStatus' => $user->kyc_status,
            'emailVerifiedAt' => $user->email_verified_at,
            'createdAt' => $user->created_at,
            'signaturesCount' => $user->signatures->count(),
            'hasCertificate' => $user->certificate !== null,
            'organizationsCount' => $user->tenants->count(),
            'currentOrganization' => $currentTenant ? [
                'id' => $currentTenant->id,
                'name' => $currentTenant->name,
                'role' => $role,
            ] : null,
            'permissions' => $permissions,
        ]);
    }
}

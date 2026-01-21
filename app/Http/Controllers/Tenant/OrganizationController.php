<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Helpers\ApiResponse;
use App\Http\Resources\TenantContextResource;
use App\Services\TenantContextService;
use App\Services\TenantService;
use App\Services\Tenant\JoinService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OrganizationController extends Controller
{
    protected TenantService $tenantService;
    protected JoinService $joinService;
    protected TenantContextService $tenantContextService;

    public function __construct(TenantService $tenantService, JoinService $joinService, TenantContextService $tenantContextService)
    {
        $this->tenantService = $tenantService;
        $this->joinService = $joinService;
        $this->tenantContextService = $tenantContextService;
    }

    /**
     * Get list of user's organizations.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $tenants = $this->tenantService->getUserTenants($user);

        return ApiResponse::success([
            'organizations' => $tenants->map(function ($tenant) use ($user) {
                $aclRole = $user->getRoleInTenant($tenant->id);
                $roleName = $aclRole ? $aclRole->name : ($tenant->pivot->role ?? 'member');
                
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'code' => $tenant->code,
                    'description' => $tenant->description,
                    'plan' => $tenant->plan,
                    'is_owner' => $tenant->owner_id === $user->id,
                    'role' => $roleName,
                ];
            }),
            'canCreate' => $user->canCreateTenant(),
        ], 'OK');
    }

    /**
     * Create a new organization.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            // DN fields for Root CA (optional)
            'company_legal_name' => 'nullable|string|max:255',
            'company_country' => 'nullable|string|max:2',
            'company_state' => 'nullable|string|max:255',
            'company_city' => 'nullable|string|max:255',
            'company_address' => 'nullable|string|max:500',
            'company_postal_code' => 'nullable|string|max:20',
            'company_organization_unit' => 'nullable|string|max:255',
        ]);

        try {
            $tenant = $this->tenantService->store(
                $request->only([
                    'name', 
                    'description',
                    'company_legal_name',
                    'company_country',
                    'company_state',
                    'company_city',
                    'company_address',
                    'company_postal_code',
                    'company_organization_unit',
                ]),
                Auth::user()
            );

            return ApiResponse::success([
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'code' => $tenant->code,
            ], 'Organization created successfully.', 201);
        } catch (\Exception $e) {
            return ApiResponse::error(__('Failed to create organization: :error', ['error' => $e->getMessage()]), 422);
        }
    }

    /**
     * Get organization details.
     */
    public function show(Tenant $organization): JsonResponse
    {
        $user = Auth::user();

        // Check if user is member
        $membership = $organization->tenantUsers()->where('user_id', $user->id)->first();
        if (!$membership) {
            return ApiResponse::error('You are not a member of this organization.', 403);
        }

        return ApiResponse::success([
            'id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'code' => $organization->code,
            'description' => $organization->description,
            'plan' => $organization->plan,
            'isOwner' => $organization->owner_id === $user->id,
            'role' => $membership->role,
            'owner' => [
                'id' => $organization->owner->id,
                'name' => $organization->owner->name,
                'email' => $organization->owner->email,
            ],
            'memberCount' => $organization->tenantUsers()->count(),
        ], 'OK');
    }

    /**
     * Update organization.
     */
    public function update(Request $request, Tenant $organization): JsonResponse
    {
        $user = Auth::user();

        // Check if user is owner or admin
        $membership = $organization->tenantUsers()
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'admin'])
            ->first();

        if (!$membership) {
            return ApiResponse::error('Only owner or admin can update organization.', 403);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $tenant = $this->tenantService->update(
            $request->only(['name', 'description']),
            $organization
        );

        return ApiResponse::success([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
        ], 'Organization updated successfully.');
    }

    /**
     * Delete organization.
     */
    public function destroy(Tenant $organization): JsonResponse
    {
        try {
            $this->tenantService->destroy($organization, Auth::user());

            return ApiResponse::success(null, 'Organization deleted successfully.');
        } catch (\Exception $e) {
            return ApiResponse::error(__('Failed to delete organization: :error', ['error' => $e->getMessage()]), 403);
        }
    }

    /**
     * Join organization by code.
     */
    public function join(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        try {
            $tenant = $this->joinService->joinByCode(
                $request->input('code'),
                Auth::user()
            );

            return ApiResponse::success([
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ], 'Joined organization successfully.');
        } catch (\Exception $e) {
            return ApiResponse::error(__('Failed to join organization: :error', ['error' => $e->getMessage()]), 422);
        }
    }

    /**
     * Switch current organization.
     */
    public function switch(Request $request)
    {
        $request->validate([
            'organization_id' => 'nullable|string',
        ]);

        $user = Auth::user();
        $tenantId = $request->input('organization_id');

        $shouldReturnJson = $request->is('api/*') || $request->expectsJson();
        $result = $this->tenantContextService->switchContext($user, $tenantId);

        if ($shouldReturnJson) {
            $data = null;
            if (!empty($result['data'])) {
                $data = new TenantContextResource($result['data']);
            }

            return ApiResponse::fromService(
                $result,
                ['mode' => $result['mode'] ?? 'personal', 'data' => $data]
            );
        }

        if (($result['status'] ?? 'success') !== 'success') {
            return back()->withErrors(['error' => $result['message'] ?? 'Error']);
        }

        return back()->with('success', $result['message'] ?? 'OK');
    }

    /**
     * Get current organization context.
     */
    public function current(): JsonResponse
    {
        $user = Auth::user();
        $result = $this->tenantContextService->getCurrentContext($user);

        $data = null;
        if (!empty($result['data'])) {
            $data = new TenantContextResource($result['data']);
        }

        return ApiResponse::fromService(
            $result,
            ['mode' => $result['mode'] ?? 'personal', 'data' => $data]
        );
    }
}
